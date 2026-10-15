# ApiGastos — Roadmap y documento de continuidad

**Última actualización:** 15 de octubre de 2026  
**Estado de referencia:** rama principal de [`igorosabel/ApiGastos`](https://github.com/igorosabel/ApiGastos), commit `4376b4eb2f07f7356265886b7d4af88e789a16e1` (`fix(spaces): proteger creación concurrente de invitaciones`).  
**Propósito:** permitir conocer qué existe, qué falta y cómo reanudar el desarrollo incluso desde una conversación nueva.  
**Naturaleza:** planificación viva; las fases futuras son propuestas, no funcionalidades ya acordadas o implementadas.

> **Regla de vigencia:** este documento es una fotografía a la fecha indicada. Antes de cualquier cambio, revisar `AGENTS.md`, este archivo, el código real y los commits nuevos. El repositorio prevalece sobre esta fotografía si hubiera diferencias.

## 1. Resumen ejecutivo

ApiGastos es la API REST de **Gastos**, aplicación libre y autoalojable para gestionar ingresos y gastos personales y compartidos. El concepto principal es el **espacio contable**: cada usuario dispone de un espacio personal y puede participar en espacios compartidos.

Se ha completado una base sólida: estructura de datos, autenticación JWT, creación y listado de espacios, invitaciones a espacios (crear, aceptar, listar, revocar) y pruebas automatizadas. El último bloque reforzó la creación de invitaciones para impedir duplicados concurrentes mediante transacciones y bloqueo de filas, con una prueba de dos conexiones a MariaDB.

**Siguiente objetivo recomendado:** administración de miembros de espacios compartidos. Después, categorías, movimientos, recurrentes e informes. El frontend Angular se desarrolla por separado.

**Lectura del progreso:** infraestructura y primer módulo de colaboración avanzados; el núcleo contable de la aplicación **aún está por desarrollar**. No se asigna un porcentaje global porque implicaría una precisión artificial: los módulos pendientes difieren mucho en complejidad.

## 2. Referencias obligatorias

- Backend: https://github.com/igorosabel/ApiGastos
- Frontend independiente: https://github.com/igorosabel/Gastos
- `AGENTS.md`: reglas obligatorias para desarrollo y asistentes.
- `docs/development/ofw-patterns.md`: patrones DTO, servicios, ORM, componentes, middleware y plantillas.
- `docs/development/testing.md`: reglas de tests y aislamiento de `gastos_test`.
- `src/Database/001_initial_schema.sql`: contrato de esquema y restricciones de negocio no cubiertas por DDL.
- `README.md`: propósito y alcance del producto.
- OFW: https://github.com/osumionline/framework/tree/main/docs/en ; contrastar con versión efectivamente instalada.
- API de referencia: https://github.com/osumionline/TPV-Backup-API (orientativa, no fuente normativa).

**Stack verificado a la fecha:** PHP 8.5+, Osumi Framework `^9.12.1`, `osumionline/plugin-token` `^3.0`, MariaDB 12.3.x, PHPUnit `^13.4`; despliegue Debian 13/Plesk. El frontend es Angular/PrimeNG, en otro repositorio y otra línea de desarrollo.

## 3. Estado real del backend

Leyenda: **HECHO** = código presente en el repositorio; **PENDIENTE** = aún sin implementación funcional; **REVISAR** = existe parte del comportamiento pero requiere decisión, revisión o pruebas adicionales.

| Área | Estado | Evidencia / alcance |
|---|---|---|
| Esquema de base de datos | HECHO | `src/Database/001_initial_schema.sql`: 8 entidades principales, índices, FKs y CHECK. |
| Modelos ORM | HECHO | `src/Model/`: `User`, `Space`, `SpaceMember`, `SpaceInvitation`, `Category`, `FinancialTransaction`, `RecurringTransaction`, `RecurringOccurrence`. Tener modelo **no** equivale a tener API. |
| Registro de usuario | HECHO | Servicios y componentes `Auth`; creación atómica de usuario, espacio personal y membresía. |
| Login JWT y usuario actual | HECHO | `/auth/login`, `/auth/me`, `LoginMiddleware` y plugin de token. |
| Crear/listar espacios | HECHO | `SpaceService`, `CreateSpace`, `GetSpaces`, componentes de modelo reutilizables. |
| Crear invitación | HECHO | Validación de propietario, correo, miembros, pendientes, token aleatorio, SHA-256 persistido y caducidad. |
| Aceptar invitación | HECHO | Coincidencia con email autenticado, transacción, membresía y consumo único; bloqueo `FOR UPDATE`. |
| Listar/revocar invitaciones | HECHO | Respuestas sin hash del token; solo propietario de espacio compartido; bloqueo en revocación. |
| Creación concurrente de invitaciones | HECHO* | Último commit introduce bloqueo del espacio y prueba de contención con dos conexiones; ver nota de límites de pruebas. |
| Gestión de miembros | PENDIENTE | Consultar miembros, salida voluntaria, expulsión, política de propietario. |
| Categorías | PENDIENTE | Modelo/esquema presentes; API CRUD y jerarquía no implementadas. |
| Movimientos económicos | PENDIENTE | Modelo/esquema presentes; API, filtros y validación monetaria no implementados. |
| Recurrentes y ocurrencias | PENDIENTE | Modelos/esquema presentes; generación y resolución ACID no implementadas. |
| Informes y previsiones | PENDIENTE | Sin endpoints de agregación. |
| Gestión completa de perfil/cuenta | PENDIENTE | Registro/login/me existen; cambios de perfil/credenciales y políticas de baja por definir. |
| Frontend Angular | FUERA DE ESTE ROADMAP | Debe planificarse en su repositorio; coordinar contratos API sin mezclar commits. |

**Nota HECHO\*:** la prueba de contención confirma que un segundo acceso espera/falla mientras otra sesión bloquea la fila de `space`, y que puede progresar al liberar el bloqueo. No sustituye una prueba extremo a extremo con dos ejecuciones PHP independientes creando simultáneamente; considerar esta prueba adicional si el nivel de robustez requerido lo justifica.

### Endpoints ya presentes

| Método y ruta | Función |
|---|---|
| `POST /auth/register` | Registrar usuario y espacio personal. |
| `POST /auth/login` | Obtener autenticación JWT. |
| `GET /auth/me` | Identidad autenticada. |
| `GET /spaces` | Listar espacios accesibles. |
| `POST /spaces` | Crear espacio compartido. |
| `POST /spaces/invitations` | Crear invitación a un espacio. |
| `POST /spaces/invitations/accept` | Aceptar invitación. |
| `GET /spaces/invitations` | Listar invitaciones de un espacio autorizado. |
| `POST /spaces/invitations/revoke` | Revocar invitación pendiente. |

Verificar las firmas, rutas y respuestas contra `src/Routes` y los componentes antes de consumirlos desde el frontend; esta tabla es orientativa.

### Última validación comunicada

El usuario confirma que **todos los tests y pruebas funcionales pasan** tras el bloque de concurrencia y que subió los cambios. No consignar un número actual de casos sin ejecutar de nuevo las suites; anteriormente se comunicaron 47 unitarios y 12 de integración, pero desde entonces se añadieron pruebas.

## 4. Roadmap priorizado

Los bloques representan unidades **funcionales y verificables**, no archivos aislados. El orden responde a dependencias: primero permisos/espacios; después categorías; luego movimientos; posteriormente automatización e informes.

| Fase | Objetivo | Estado | Entregable y criterio de cierre |
|---|---|---|---|
| 0. Cimientos técnicos | Esquema, ORM, OFW, PHPUnit, convenciones | COMPLETADA | Modelos, configuración de pruebas, `AGENTS.md` y guías. |
| 1. Identidad y autenticación | Registro, login, `/me`, middleware | COMPLETADA | JWT y creación del espacio personal probados. |
| 2. Espacios e invitaciones | Espacios; crear/aceptar/listar/revocar invitaciones; concurrencia de creación | COMPLETADA EN ALCANCE ACTUAL | Endpoints, controles de propietario, transacciones y suites en verde. |
| **3. Miembros de espacios** | Listado y administración de membresías | **SIGUIENTE** | API segura para listar, abandonar y expulsar; política de propietario definida; tests e HTTP. |
| 4. Categorías | Categorías por espacio y árbol jerárquico | PENDIENTE | CRUD, orden, jerarquía, comprobación de ciclos y aislamiento. |
| 5. Movimientos | Alta, consulta, edición y política de eliminación de ingresos/gastos | PENDIENTE | Importes DECIMAL string, filtros, categorías válidas, permisos y tests. |
| 6. Recurrentes | Plantillas, generación mensual y ocurrencias | PENDIENTE | Generación idempotente; confirmación/omisión ACID; política temporal. |
| 7. Informes | Resúmenes mensuales, categorías, saldos y previsiones | PENDIENTE | Agregados correctos, sin contaminación entre espacios, pruebas con casos límite. |
| 8. Cierre de API / hardening | Auditoría, seguridad, documentación de contratos, rendimiento, despliegue | PENDIENTE | Criterios de release, pruebas de regresión, procedimientos y documentación. |
| 9. Evolución posterior | Correo de invitaciones, perfil avanzado, mejoras UX/API, etc. | POR PRIORIZAR | Solo cuando se acuerde necesidad y diseño. |

Las fases son orden sugerido, no una obligación rígida. Cambiar el orden exige documentar dependencias y motivo.

## 5. Propuesta de desglose por fase

### Fase 3 — Gestión de miembros: siguiente trabajo

**Objetivo:** completar la colaboración dentro de un espacio compartido antes de empezar el módulo contable.

- **3.1 Listar miembros** de un espacio del que el solicitante forma parte; decidir explícitamente qué atributos son públicos para miembros (id, nombre, correo, fecha de entrada y propietario). Diferenciar permisos de miembro y propietario.
- **3.2 Abandono voluntario** de un espacio compartido por un miembro no propietario, siempre que las restricciones de integridad lo permitan.
- **3.3 Expulsión** de otro miembro por parte del propietario con control de autorizaciones y política explícita para historial.
- **3.4 Política de propiedad:** decidir si se permite transferencia de propietario, quién puede abandonar un espacio si lo posee y qué sucede con espacios personales. No implementar transferencia por suposición.

**Advertencia clave detectada en DDL:** `financial_transaction` tiene una FK compuesta `(id_space, id_user)` hacia `space_member`. Cuando existan movimientos históricos, borrar físicamente una membresía puede estar impedido y podría ser incorrecto conceptualmente. **Antes de implementar abandono o expulsión hay que decidir una política de membresías inactivas/históricas**, que puede requerir una migración y regeneración revisada de modelos. No hacer borrado en cascada de historial. Este es el primer tema arquitectónico a tratar en el próximo bloque.

**Recomendación operativa:** primera unidad funcional de fase 3: **listado de miembros**, con DTO, servicio, componente(s) y tests unitarios/integración/HTTP. Después abordar altas/bajas con la política resuelta. No dividir el listado en mensajes por archivo.

### Fase 4 — Categorías

- Listar categorías del espacio; crear raíz e hijas; modificar nombre, tipo y orden; definir política de borrado con movimientos/recurrentes asociados.
- Validar siempre membresía y que `id_parent` pertenece al mismo espacio; bloquear ciclos indirectos y autorefencias.
- Respetar `default_type` (`expense`/`income`), `sort_order` y FK compuesta del esquema.
- Pruebas de árbol, aislamiento entre espacios, permisos y restricciones de borrado.

### Fase 5 — Movimientos

- CRUD y búsquedas paginadas/filtradas por espacio, rango de fechas, categoría, tipo y usuario cuando corresponda.
- Mantener `amount` como string decimal, nunca float; validar escala y positividad, tipo compatible con categoría según política acordada.
- Verificar que categoría, miembro y movimiento pertenecen al mismo espacio; controlar edición de movimientos asociados a ocurrencias.
- Definir explícitamente estrategia de borrado/archivo y auditoría antes de su implementación.
- Testear precisión monetaria, límites, ordenación, paginación, permisos y carreras críticas.

### Fase 6 — Recurrentes y ocurrencias

- CRUD de plantillas recurrentes y política de cambios retroactivos.
- Generación idempotente por período (`uq_occurrence_period`), con fechas reales para días 29/30/31 en meses cortos según regla de negocio aún por decidir.
- Respetar inicio/fin, mes mínimo de creación y snapshots de categoría/tipo/concepto/importe.
- Confirmación: crear movimiento y resolver ocurrencia en una única transacción con bloqueo; omisión sin movimiento.
- No modificar ocurrencias ya confirmadas al editar su plantilla.
- Tests de cambios de mes/año, febrero bisiesto, duplicados y concurrencia.

### Fase 7 — Informes

- Definir semántica de saldo inicial/final, ingresos, gastos, agrupación por mes y categoría, evolución y previsión.
- Distinguir movimientos confirmados frente a ocurrencias pendientes para evitar doble contabilización.
- Agregar con precisión decimal y aislar siempre los datos por espacio.
- Verificar resultados con casos de prueba contables reproducibles.

### Fase 8 — Preparación de una release

- Revisar política de errores HTTP, autorización transversal, protección de secretos, integridad referencial y casos de concurrencia.
- Documentar API y ejemplos de peticiones/respuestas; considerar contrato OpenAPI cuando el volumen de endpoints lo haga útil.
- Evaluar índices, consultas N+1, paginación, logs privados, migraciones y compatibilidad de versiones.
- Ensayar despliegue y actualización en Plesk sin afectar datos de producción.
- Revisar README, licencia y versión inicial de publicación.

## 6. Decisiones ya tomadas que se deben preservar

1. **Separación backend/frontend**: API OFW en este repositorio, Angular/PrimeNG en `igorosabel/Gastos`.
2. **No inventar convenciones de OFW**: documentación oficial y código real primero; no modificar el framework sin debatirlo.
3. **DTO `ODTO` y `#[ODTOField]`** en entradas estructuradas. Identidad del usuario desde `LoginMiddleware`, no desde JSON.
4. **Componentes `OComponent`** de orquestación y plantillas `.json`; componentes anidados reutilizables para entidades/listados.
5. **Servicios `OService`** como sede de reglas de negocio; inyección mediante `inject()` en componentes.
6. **ORM prioritario** (`where`, `findOne`, `save`); SQL directo si hay necesidad demostrable, especialmente bloqueos `FOR UPDATE` o consultas complejas.
7. **Transacciones ACID** y una sola conexión compartida por ORM y SQL en la misma operación.
8. **Dinero `DECIMAL`/string** y no `float`.
9. **Seguridad por espacio** comprobada en API, incluyendo permisos de propietario y pertenencia.
10. **Invitaciones:** 32 bytes aleatorios serializados como hexadecimal, SHA-256 guardado, token solo en creación, validez de 7 días, email destinatario coincidente, uso único, revocación y estados históricos.
11. **Pruebas:** PHPUnit 13, unitarias sin BD, integración exclusivamente en `gastos_test`, aislamiento y limpieza. `createStub()` para comportamiento, `createMock()` para expectativas.
12. **Trabajo incremental pero autocontenido:** cada entrega agrupa implementación, DTO, rutas, serialización, tests y comprobación funcional; no un archivo por mensaje. No avanzar con suites en rojo o con notices.
13. **PHP 8.5+**, `strict_types`, PHPDoc en métodos, sencillez de diseño y compatibilidad con código existente.
14. **Modelos generados:** no modificar a mano sin justificación y revisión de efectos de regeneración.
15. **Datos históricos:** no realizar eliminaciones físicas sin política explícita.

## 7. Puntos abiertos y riesgos

| Tema | Cuándo resolverlo | Por qué importa |
|---|---|---|
| Membresías inactivas frente a borrado físico | **Antes de abandono/expulsión** | Mantener atribución histórica y respetar FK de transacciones. |
| Transferencia de propietario | Durante gestión de miembros | Impide dejar espacios sin responsable. |
| Visibilidad del correo de otros miembros | Antes del endpoint de listado | Privacidad y minimización de datos. |
| Invitación por correo electrónico real | Fase posterior, si se prioriza | Ahora se comparte manualmente el token. |
| Doble creación realmente paralela de invitaciones | Durante hardening, si procede | La prueba actual demuestra contención, no dos procesos PHP completos. |
| Semántica de categorías y borrado | Antes de CRUD completo | Transacciones y recurrentes referencian categorías. |
| Edición/borrado de movimientos con historial | Antes de implementar esas acciones | Consistencia, trazabilidad e integridad. |
| Días 29–31 en recurrentes | Antes del generador mensual | Política de calendario. |
| Zona horaria: PHP, BD, UTC y horarios de expiración | Antes de reforzar fechas/recurrentes | Evitar diferencias entre servidor y base de datos. |
| Perfil, bajas de cuenta y anonimización | Antes de release | Datos con historial; no borrar a ciegas. |
| Contratos backend/frontend | Al conectar Angular | Evitar discrepancias de nombres, errores y estados. |

No tomar estas decisiones implícitamente durante la implementación: consensuarlas y documentarlas.

## 8. Cómo trabajamos en cada bloque

1. **Inspección**: leer `AGENTS.md`, este documento, las guías y los archivos relevantes del commit actual.
2. **Diseño breve**: concretar alcance, permisos, respuesta HTTP, transacciones y política de datos; plantear alternativas si hay decisiones abiertas.
3. **Entrega completa**: proponer el conjunto coherente de código y pruebas, independientemente del número de archivos.
4. **Validación del usuario**: ejecutar pruebas y Postman; revisar avisos de PHPUnit, formatos JSON, datos persistidos y permisos.
5. **Corrección**: resolver errores dentro del mismo bloque sin introducir cambios al framework por comodidad.
6. **Cierre**: un commit lógico, actualizar este documento con el resultado y marcar el siguiente objetivo.

**Comandos disponibles según `composer.json`:**

```bash
composer test
composer test:integration
composer test:all
```

Antes de tests de integración: comprobar variables `GASTOS_TEST_DSN`, `GASTOS_TEST_USER`, `GASTOS_TEST_PASSWORD` fuera del repositorio y destino `gastos_test`. Nunca cargar secretos de producción en tests.

**Convenciones de implementación:** DTOs en `src/DTO`, componentes de rutas en `src/Module`, representaciones reutilizables en `src/Component/Model`, servicios en `src/Service`, rutas en `src/Routes`, modelos en `src/Model`, unit tests en `tests/Unit`, integración en `tests/Integration` y esquema en `src/Database`.

## 9. Instrucciones para retomar en una conversación nueva

Se puede empezar con un mensaje como este:

> Estamos desarrollando **ApiGastos** (`https://github.com/igorosabel/ApiGastos`), backend PHP 8.5+ / OFW 9.12.x / MariaDB. Lee primero `AGENTS.md`, `docs/development/CONTINUIDAD_APIGASTOS.md`, `docs/development/ofw-patterns.md`, `docs/development/testing.md` y el código/commits actuales. Usa el documento de continuidad como roadmap, **pero verifica cada afirmación contra el repositorio**. El último trabajo conocido es el bloqueo de creación concurrente de invitaciones (commit `4376b4e`). Seguimos con gestión de miembros; antes de bajas/expulsiones hay que resolver la persistencia histórica de membresías. No quiero desarrollo por archivo: cada bloque debe incluir implementación completa, tests y comprobación funcional. No cambies OFW sin discutirlo. Propón el siguiente bloque autocontenido según el estado real de GitHub.

### Procedimiento de reanudación del asistente

1. Consultar GitHub y leer `AGENTS.md` **antes** de proponer código.
2. Leer el último commit y detectar si este documento está desactualizado; no asumir que el último SHA aquí escrito sigue siendo HEAD.
3. Inspeccionar las entidades, componentes, servicios, rutas y tests existentes del bloque en curso.
4. Consultar documentación real de OFW si hay dudas sobre ORM, DTO, middleware, plantilla o inyección.
5. Resumir **hecho / pendiente / decisión abierta / siguiente bloque**, sin repetir funcionalidad ya implementada.
6. Presentar una unidad funcional completa con tests, esperar validación y no presuponer que algo está subido hasta comprobarlo.
7. Actualizar este documento después de cerrar cada bloque importante.

## 10. Registro de hitos y mantenimiento

| Fecha | Hito | Referencia |
|---|---|---|
| Hasta octubre de 2026 | Esquema, auth JWT, espacios, invitaciones y primeras pruebas | Historial Git de `ApiGastos` |
| 15-10-2026 | Patrones OFW y normas de trabajo documentadas | `AGENTS.md`, `docs/development/` |
| 15-10-2026 | Creación, aceptación, consulta y revocación de invitaciones; refuerzo de concurrencia | HEAD `4376b4eb2f07f7356265886b7d4af88e789a16e1` |
| Próximo | Gestión de miembros, comenzando por política de historial y listado | Pendiente |

**Al actualizar:** cambiar fecha y SHA verificado; actualizar tabla de estados y fases; consignar tests ejecutados (con número solo si está confirmado); anotar decisiones nuevas, migraciones y riesgos resueltos; señalar el próximo bloque exactamente. No reescribir el historial para presentar como terminado algo que no lo esté.

---

**Conclusión:** la base de API y colaboración ya existe y está probada; el mayor trabajo restante es el **núcleo contable** (categorías, movimientos, recurrentes, informes), seguido del cierre de seguridad, documentación y despliegue. El desarrollo continuará por unidades completas y comprobables, priorizando integridad de datos y separación entre espacios.

# ApiGastos — Roadmap y documento de continuidad

**Actualización:** 15 de octubre de 2026  
**Repositorio:** https://github.com/igorosabel/ApiGastos  
**Estado verificado:** rama `main`, commit **`4f924b8554`** (`feat(recurring): filtrar y paginar ocurrencias`).  
**Siguiente bloque propuesto:** fase 7, informes económicos mensuales basados exclusivamente en movimientos reales.  
**Ubicación:** `docs/development/CONTINUIDAD_APIGASTOS.md` (sustituir el documento anterior).

> Este documento es una fotografía de continuidad, no una fuente más autorizada que el código. **Al retomar hay que volver a leer `AGENTS.md`, consultar HEAD, los archivos reales y `docs/development/`.** Las pruebas mencionadas fueron ejecutadas y declaradas satisfactorias por el desarrollador; no se han ejecutado desde la elaboración de esta versión.

## 1. Qué es Gastos

Gastos es una aplicación libre y autoalojable de contabilidad doméstica y compartida. **ApiGastos** es su backend REST en PHP y Osumi Framework (OFW). El frontend **Angular/PrimeNG** reside por separado en https://github.com/igorosabel/Gastos. No mezclar cambios de ambos repositorios ni asumir que el frontend ya consume los endpoints actuales.

Cada persona posee un espacio personal y puede compartir otros espacios; categorías, movimientos económicos, plantillas recurrentes y ocurrencias se aíslan estrictamente por `id_space`.

## 2. Tecnologías y normas de trabajo

| Aspecto | Estado |
|---|---|
| PHP | `>=8.5` |
| Framework | `osumionline/framework: ^9.12.1` |
| JWT | `osumionline/plugin-token: ^3.0` |
| BD | MariaDB 12.3.x, InnoDB, utf8mb4 |
| Tests | PHPUnit `^13.4`; suites Unit e Integration |
| Entorno | Debian 13 / Plesk |
| Autoload | `Osumi\OsumiFramework\App\` → `src/` |
| Esquema inicial | `src/Database/001_initial_schema.sql` |
| Normas | `AGENTS.md`, `docs/development/ofw-patterns.md`, `docs/development/testing.md` |

**Flujo obligatorio:** revisar código/documentación del HEAD; proponer un bloque funcional pequeño pero completo, incluyendo archivos nuevos, cambios y tests; el desarrollador lo aplica, pasa las pruebas y comprueba HTTP, sube un commit y solo entonces se continúa. No afirmar que un test ha pasado sin que el usuario lo confirme. No modificar OFW para solucionar Gastos sin discutir previamente si el cambio pertenece al framework.

**Convenciones OFW:** DTO `ODTO` + atributos `#[ODTOField]`; identidad `idUser` procedente de `LoginMiddleware` mediante contexto de middleware (nunca del cliente); `OComponent` con servicios obtenidos mediante `inject()`; plantilla `.json` con pipes OFW (`plain`, `bool`, `number`); representación de listas con componentes modelo/listado. Servicios `OService`, ORM `OModel`/`where()`/`save()` primero, SQL directo únicamente para consultas/operaciones que lo necesitan. PHP `declare(strict_types=1)`, PHPDoc y tipos. Para transacciones utilizar **la conexión compartida** `ODB::getInstance()` y verificar que ORM participa en ella. Importes `DECIMAL(12,2)` en MariaDB y **strings exactos en PHP; nunca floats monetarios**.

## 3. Estado de las fases

| Fase | Estado al HEAD verificado | Implementación |
|---|---|---|
| 0. Base técnica | Completada | Esquema, modelos OFW, configuración, tests |
| 1. Autenticación | Completada en alcance inicial | Registro atómico, espacio personal, login JWT, usuario actual |
| 2. Espacios e invitaciones | Completada en alcance inicial | Espacios compartidos, invitación/aceptación/revocación/listados y protecciones de concurrencia |
| 3. Miembros | Completada en alcance inicial | Consulta, salida, expulsión, baja lógica y reactivación; transferencia de propiedad no definida |
| 4. Categorías | CRUD básico completo | Árbol jerárquico, tipos, actualización y reordenación sin ciclos, borrado restringido |
| 5. Movimientos | CRUD básico completo | Crear, consultar con filtros/paginación, editar, eliminar independientes; proteger los vinculados a recurrentes |
| 6. Recurrentes | **Ciclo principal implementado** | Plantillas, activación, generación por plantilla y espacio, instantáneas, consulta filtrada/paginada, confirmación/omisión ACID |
| 7. Informes y previsiones | **Pendiente: siguiente fase** | Resumen mensual, desglose por categoría, comparativas, previsiones separadas de contabilidad real |
| 8. Hardening, documentación y release | Pendiente | Concurrencia reforzada, contratos, rendimiento, instalación y versión estable |

**Último bloque completado y validado por el usuario:** bloque 25, filtros y paginación de ocurrencias (`4f924b8554`).

## 4. Rutas HTTP implementadas (28)

Las siguientes se han contrastado con `src/Routes/{Auth,Space,Category,Transaction,Recurring}.php` de `main`. Salvo registro y login, están protegidas con `LoginMiddleware`.

| Método | Ruta | Función |
|---|---|---|
| POST | `/auth/register` | Registro; espacio personal y membresía |
| POST | `/auth/login` | Autenticación JWT |
| GET | `/auth/me` | Usuario actual |
| GET | `/spaces` | Espacios del usuario |
| POST | `/spaces` | Crear espacio compartido |
| POST | `/spaces/invitations` | Crear invitación |
| POST | `/spaces/invitations/accept` | Aceptar invitación |
| GET | `/spaces/invitations` | Consultar invitaciones |
| POST | `/spaces/invitations/revoke` | Revocar invitación |
| GET | `/spaces/members` | Consultar miembros |
| POST | `/spaces/members/leave` | Abandonar espacio |
| POST | `/spaces/members/remove` | Expulsar miembro |
| GET | `/categories` | Listar categorías |
| POST | `/categories` | Crear categoría |
| POST | `/categories/update` | Editar/reorganizar categoría |
| POST | `/categories/delete` | Borrar categoría sin dependencias |
| GET | `/transactions` | Movimientos filtrados/paginados |
| POST | `/transactions` | Registrar ingreso/gasto |
| POST | `/transactions/update` | Editar movimiento independiente |
| POST | `/transactions/delete` | Borrar movimiento independiente |
| GET | `/recurring` | Plantillas del espacio (activas e inactivas) |
| POST | `/recurring` | Crear plantilla |
| POST | `/recurring/update` | Editar plantilla |
| POST | `/recurring/status` | Activar/desactivar plantilla |
| POST | `/recurring/generate` | Generar ocurrencia de plantilla/mes |
| POST | `/recurring/generate-space` | Generar mes para plantillas activas del espacio |
| GET | `/recurring/occurrences` | Filtrar/paginar ocurrencias |
| POST | `/recurring/occurrences/resolve` | Confirmar u omitir ocurrencia |

**Inventario:** 28 rutas (3 Auth + 9 Space + 4 Category + 4 Transaction + 8 Recurring). La ruta de página inicial (`src/Routes/Home.php`) queda fuera del inventario REST.

### Contratos relevantes

**`GET /transactions`**: `spaceId` obligatorio; `dateFrom`, `dateTo` inclusive (`YYYY-MM-DD`), `type` (`expense`/`income`), `categoryId`, `page` por defecto 1, `pageSize` por defecto 20 (máximo 100; página máxima 100000). Orden `date DESC, id DESC`; responde `transactions`, `total`, `page`, `page_size`, `total_pages`, `success`, `error`, `message`.

**`POST /transactions`**: categoría del mismo espacio y tipo; importe positivo `string` con dos decimales; fecha válida; concepto opcional hasta 255. El autor se deriva de middleware. **Edición** mantiene `id`, `id_space`, `id_user`; **edición y borrado** rechazan movimientos relacionados con cualquier `recurring_occurrence` (`409`). El borrado de movimientos independientes es físico e irreversible según política actual.

**Plantillas recurrentes**: `spaceId`, `categoryId`, `type`, `amount` string, `concept` obligatorio, `dayOfMonth` 1–31, `startDate`, `endDate` opcional y `active`. Las modificaciones futuras NO reescriben las instantáneas de ocurrencias ya creadas. Las plantillas inactivas siguen apareciendo en GET.

**`POST /recurring/generate`**: `spaceId`, `recurringId`, `period` como `YYYY-MM-01`. Ajusta días 29–31 al último del mes; nunca genera antes del mes de creación ni antes de `startDate`, ni después de `endDate`. Una ocurrencia nueva se guarda `pending`, con instantánea de concepto, categoría, tipo, importe, fecha y sin transacción real. Primera creación: HTTP 201, `created=true`; reintento: 200, `created=false` y mismos datos incluso si cambió la plantilla o se resolvió. Restricción BD única `(id_recurring_transaction,period)`.

**`POST /recurring/generate-space`**: `spaceId`, `period`; evalúa plantillas activas; devuelve recuentos `created`, `existing`, `skipped`, `total` (plantillas activas evaluadas). Reutiliza la generación individual. **No es una transacción global del lote**: cada plantilla se confirma individualmente, reintentable e idempotente; un fallo intermedio puede dejar algunas generadas. Las plantillas inactivas no entran en `total`; `skipped` alude a plantillas fuera de fecha, **no** a ocurrencias con estado `skipped`.

**`POST /recurring/occurrences/resolve`**: `spaceId`, `occurrenceId`, `action` `confirm` o `skip`. La confirmación crea exactamente un `financial_transaction` usando la instantánea y al usuario que confirma como autor; guarda `id_transaction`, marca `confirmed` y rellena `resolved_at` **en la misma transacción ACID**. Omitir marca `skipped` y no crea movimiento. Resolver otra vez devuelve conflicto 409. Una plantilla desactivada no invalida ocurrencias pendientes existentes.

**`GET /recurring/occurrences`**: `spaceId` obligatorio; opcionales `status` (`pending`/`confirmed`/`skipped`), `periodFrom`, `periodTo` (`YYYY-MM-01`, ambos incluidos), `recurringId`, `page` (1), `pageSize` (20; máximo 100; página máxima 100000). Filtro por **periodo**, no por fecha programada. Orden `scheduled_date DESC, id DESC`. Responde `occurrences`, `total`, `page`, `page_size`, `total_pages`, `success`, `error`, `message`. `OccurrenceService::getBySpace()` original se conserva para compatibilidad, mientras la ruta HTTP usa `search()`.

**Errores:** entradas inválidas 400; ausencia de permiso/membresía activa 403; conflicto de estado/referencias 409. Leer el componente real para identificar campos exactos `error/message` y la cobertura HTTP en cada endpoint.

## 5. Esquema y reglas de integridad

Tablas existentes en `src/Database/001_initial_schema.sql` y modelos generados en `src/Model/`:

- `user`; `space` (personal/compartido, dueño); `space_member` (clave única espacio/usuario, `left_at`); `space_invitation` (token hash, expiración/resolución).
- `category` (padre, orden, tipo inmutable); `financial_transaction` (importe exacto, autor, fecha, categoría, espacio).
- `recurring_transaction` (plantilla mensual, fechas, activación); `recurring_occurrence` (instantánea histórica, periodo, estado, resolución y vínculo eventual con movimiento).

**Aislamiento:** FKs compuestas `(id_space,id_category)`, `(id_space,id_transaction)` donde corresponda; comprobar pertenencia y tipo también en servicios. No hay que regenerar modelos sin revisar diferencias, restricciones y comportamiento de OFW. No se presupone un directorio de migraciones incrementales de Gastos.

**Concurrencia:** operaciones que escriben suelen bloquear `space` (`SELECT ... FOR UPDATE`) antes de miembros, plantillas, movimientos u ocurrencias. Confirmación de ocurrencia es atómica; clave única protege duplicados. Mantener un orden de bloqueos coherente ante futuros cambios. La búsqueda paginada (transacciones/ocurrencias) usa consultas separadas `COUNT` y lista; no promete una instantánea estricta si hay escrituras concurrentes.

**Datos con historial:** `space_member.left_at` desactiva y permite reactivación conservando la fila (no registra todos los periodos de membresía). No borrar físicamente categorías con dependencias ni movimientos vinculados a ocurrencias; no borrar/anonimizar usuarios y espacios con historial sin política explícita.

## 6. Organización del código

```text
src/
  Routes/                   Auth, Space, Category, Transaction, Recurring
  DTO/{Space,Category,Transaction,Recurring}/
  Service/Auth/             AuthService, RegistrationService, RegistrationValidator
  Service/Space/            SpaceService, SpaceInvitationService, SpaceMemberService
  Service/Category/         CategoryService
  Service/Transaction/      TransactionService
  Service/Recurring/        RecurringService, OccurrenceService, SpaceOccurrenceService
  Module/{Auth,Space,Category,Transaction,Recurring}/
  Component/Model/          Space, SpaceList, SpaceInvitation, SpaceInvitationList,
                            SpaceMember, SpaceMemberList, Category, CategoryList,
                            Transaction, TransactionList, Recurring, RecurringList,
                            Occurrence, OccurrenceList
  Model/                    8 modelos de entidad
  Database/                 001_initial_schema.sql

tests/
  bootstrap.php
  Unit/{Auth,Space,Category,Transaction,Recurring}/
  Integration/{Auth,Space,Category,Transaction,Recurring}/
  Integration/OrmIntegrationTestCase.php
```

La organización es PSR-4; no volver a poner los DTO en una carpeta plana. Integraciones por dominio cargan `require_once dirname(__DIR__) . '/OrmIntegrationTestCase.php';`. Las rutas `src/Routes/*.php` siguen el prefijo y middleware OFW; no inventar convenciones de un proyecto distinto.

## 7. Estrategia de pruebas

Comandos reales de `composer.json`:

```bash
composer test
composer test:integration
composer test:all
```

Unit no usa MariaDB. Integration utiliza **únicamente `gastos_test`** y las variables `GASTOS_TEST_DSN`, `GASTOS_TEST_USER`, `GASTOS_TEST_PASSWORD`; la base compartida `OrmIntegrationTestCase` verifica la conexión antes de escrituras y prepara el contexto OFW. Cada clase elimina sus registros en orden seguro para FKs. Las comprobaciones funcionales HTTP se realizan aparte. Sin errores, fallos, warnings o notices.

**Pruebas presentes:** autenticación y middleware; espacios/invitaciones/miembros; árbol de categorías; movimientos CRUD y paginación; plantillas recurrentes y cambios de estado; generación individual y masiva; instantáneas, meses cortos, fechas y permisos; resolución ACID y estados irreversibles; búsqueda paginada/filtrada de ocurrencias. El desarrollador ha comunicado que **todos los tests y pruebas del bloque 25 pasaron y el commit fue subido**. Esta actualización no ejecuta PHPUnit ni dispone de recuento de casos CI.

Casos de mayor riesgo a continuar verificando: doble confirmación concurrente, solapamiento generación/edición/desactivación, rollback real al fallar escritura intermedia, diferentes zonas horarias entre PHP/BD y cambio de mes, y rendimiento con cientos/miles de plantillas/ocurrencias.

## 8. Hitos desde la continuidad anterior

La continuidad anterior estaba anclada en `339284b` (filtros y paginación de movimientos). Desde allí se han subido:

| Commit | Resultado |
|---|---|
| `87ebbcb` | Actualización previa del documento de continuidad |
| `5135da7` | Edición de movimientos económicos |
| `1d6b8b2` | Eliminación segura de movimientos independientes |
| `303f575` | Crear y listar plantillas recurrentes |
| `256bff2` | Editar, desactivar y reactivar plantillas |
| `e3a355e` | Generación mensual individual idempotente |
| `d48a383` | Consultar y confirmar/omitir ocurrencias |
| `c247b13` | Generar un mes para plantillas activas del espacio |
| **`4f924b8`** | **Filtros y paginación de ocurrencias; HEAD verificado** |

Los cambios anteriores a la continuidad previa comprenden registro/login, gestión de espacios/invitaciones, miembros, categorías y creación/listado/filtros de movimientos.

## 9. Qué queda por hacer: roadmap prioritario

### Siguiente bloque recomendado — 26: informe mensual básico

Implementar un servicio de informes y un endpoint **de solo lectura** para obtener ingresos, gastos y balance de un espacio para un mes solicitado (`YYYY-MM-01`). Verificar diseño del contrato antes de codificar y seguir patrones OFW de DTO/componente/servicio. Reglas esenciales:

1. Requerir miembro activo y filtrar siempre por `id_space`.
2. Agregar **exclusivamente** `financial_transaction` por `date`, independientemente de si se creó manualmente o al confirmar una recurrencia. No sumar `recurring_occurrence` para el resultado real: se duplicaría el confirmado y contaminarían el balance las pendientes.
3. `SUM(amount)` mediante SQL `DECIMAL`, conversión y operación exactas; **nunca `float`**. Definir representación de `0.00` cuando no hay filas.
4. Separar `income`, `expense`, `balance` y tests para mes vacío, importes exactos, varios espacios y estados de recurrentes.
5. Verificar comportamiento y protección de endpoints HTTP; no realizar migraciones si no son necesarias.

### Continuación de informes

- Desglose por categorías, respetando árbol y tipo; decidir si agrupar por hoja o incluir agregados de padres sin doble contabilizar.
- Evolución/comparación entre meses y periodos; filtros y paginación/limitaciones según necesidad real.
- Previsiones en endpoint/estructura **separados** de contabilidad real; distinguir estimado (`pending`) y confirmado (`financial_transaction`), sin duplicados.
- Considerar representación de saldos acumulados y límites de consulta.

### Hardening / release

- Pruebas de concurrencia reales con conexiones simultáneas; reevaluar generación masiva ante cambios concurrentes (es por plantilla, no globalmente atómica).
- Decidir automatización/calendario de generación: actualmente la generación es **explícita** por HTTP, no un cron documentado ni algo automático. Aclarar políticas para pausas/reactivaciones (no se conserva el historial completo de activación).
- Revisión de índices y consultas de listados grandes (`GET /recurring` aún devuelve todas las plantillas; `getBySpace()` de ocurrencias sigue existiendo sin paginación para uso interno).
- Contratos de error, seguridad, cambios de esquema/migraciones, despliegue, ejemplos HTTP y guía de instalación; actualizar README (su apartado «Estado del proyecto» está desfasado: declara como futura funcionalidad lo ya implementado).
- Pruebas e2e, estrategia de releases, datos de ejemplo y política de actualización de instalaciones existentes.

### Decisiones de producto/arquitectura abiertas

- Historial/auditoría o reversión de borrados físicos de movimientos independientes; actualmente no hay papelera.
- Transferencia de propiedad, salida del dueño y eliminación/anonimización de cuentas con historial.
- Registro completo de entradas/salidas de miembros, más allá de la fila `left_at` reactivable.
- Reactivación de plantillas con historial de periodos pausados: el esquema no permite reconstruir exactamente pausas anteriores.
- Política de generación futura/retroactiva y eventual scheduler (no inventar un job automático).
- Edición/ajuste de una ocurrencia pendiente frente a modificar la plantilla: hoy la instantánea se conserva; requerirá una decisión específica.
- Escalabilidad futura del lote y consistencia de los contadores bajo actualizaciones concurrentes.
- Autenticación y gestión de cuenta ampliada (perfil, contraseña y opciones de usuario) fuera del alcance inicial.

## 10. Cómo retomar desde una conversación nueva

Mensaje orientativo:

> Estamos desarrollando el backend **ApiGastos** (`https://github.com/igorosabel/ApiGastos`) de la aplicación Gastos. Lee **primero** `AGENTS.md`, `docs/development/CONTINUIDAD_APIGASTOS.md` y `docs/development/{ofw-patterns,testing}.md`, y consulta el HEAD y el código actual. La última referencia verificada es `4f924b8554` del 15/10/2026: ya están terminados autenticación inicial, espacios, invitaciones, miembros, categorías, CRUD de movimientos, plantillas recurrentes, generación individual/por espacio, resolución y búsqueda paginada de ocurrencias. El siguiente bloque previsto es el **informe mensual de ingresos, gastos y balance**, calculado solo sobre `financial_transaction` con importes exactos. No supongas que el commit sigue siendo HEAD; contrástalo. Trabajamos en bloques funcionales completos con código, pruebas unitarias, integración y comprobación HTTP; yo ejecuto los tests y subo el commit antes de avanzar. El frontend Angular va separado.

**En cada reanudación:** revisar commits posteriores a `4f924b8`; leer los archivos reales de rutas y servicios afectados, modelos/esquema, DTO/componentes/tests; decidir contrato del siguiente informe sobre evidencia; proponer código y tests completos; esperar validación. No asumir que el documento sustituye a la fuente viva.

---

**Fin de fotografía: `4f924b8554`, 15 de octubre de 2026.**

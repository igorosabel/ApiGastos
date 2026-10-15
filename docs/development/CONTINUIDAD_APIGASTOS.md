# ApiGastos — Roadmap y documento de continuidad

**Última actualización:** 15 de octubre de 2026  
**Estado de referencia:** rama `main` de [igorosabel/ApiGastos](https://github.com/igorosabel/ApiGastos), commit **`339284b53fa6570b6778002546d5312c57d5c61f`** (`feat(transactions): añadir filtros y paginación de movimientos`).  
**Propósito:** permitir retomar el desarrollo desde cero, en otra conversación o por otro desarrollador, distinguiendo el código implementado de las decisiones pendientes.  
**Ubicación propuesta:** `docs/development/CONTINUIDAD_APIGASTOS.md` (sustituye la versión anterior).

> **Vigencia:** este archivo describe una fotografía del repositorio y recoge las últimas validaciones comunicadas. Ante diferencias, **prevalecen `AGENTS.md`, el código vivo y las pruebas**. Leerlos de nuevo antes de proponer cambios; nunca deducir el estado actual exclusivamente de este documento.

## 1. Resumen ejecutivo y punto exacto de reanudación

**ApiGastos** es la API REST de **Gastos**, aplicación libre y autoalojable para ingresos y gastos personales y compartidos. Cada usuario dispone de un **espacio personal**, y puede pertenecer a uno o más **espacios compartidos**. Los datos se aíslan por espacio.

**Implementado y validado hasta el commit de referencia:** registro/login/JWT/usuario actual; creación y listado de espacios; invitaciones a espacios compartidos (crear, aceptar, listar, revocar, protección de duplicados concurrentes); consulta de miembros; salida voluntaria y expulsión con baja lógica y reincorporación; categorías jerárquicas (crear, listar, editar, mover sin ciclos, eliminar si no tienen dependencias); movimientos reales de ingresos y gastos (crear, listar, filtrar y paginar). DTO y pruebas ya se organizan por dominios.

**Último bloque completado:** filtros y paginación de `GET /transactions`, commit `339284b`. El usuario comunicó que **todos los tests y pruebas funcionales pasaron**, y que los cambios fueron subidos al repositorio. Esto no equivale a que este documento haya ejecutado las pruebas localmente.

**Siguiente bloque recomendado:** edición de movimientos (`POST /transactions/update` o ruta a convenir siguiendo las convenciones actuales), con validación del espacio, categoría, precisión monetaria, membresía activa, identidad/autorización y compatibilidad con movimientos vinculados a ocurrencias recurrentes. Después, política de eliminación, y a continuación recurrentes e informes. **No hay código de edición de movimientos en el commit de referencia.**

**No mezclar frontend:** Angular/PrimeNG reside en [igorosabel/Gastos](https://github.com/igorosabel/Gastos), otro repositorio con su propio desarrollo.

## 2. Reglas obligatorias de colaboración

1. Antes de cualquier modificación de código, leer **`AGENTS.md` del repositorio vigente**, `docs/development/ofw-patterns.md`, `docs/development/testing.md` y los archivos reales afectados. Consultar la documentación/código fuente de OFW cuando una API no esté contrastada.
2. Desarrollo incremental en **bloques funcionalmente completos**. Cada entrega debe incluir **todos los archivos, cambios y tests necesarios**, con contenido completo para archivos nuevos; no dejar instrucciones vagas de «crear un test» o «añadir algo similar». Si se cambia un método difícil de integrar, ofrecerlo íntegro.
3. Entregar el código y las pruebas antes de avanzar; el usuario lo implementa, verifica, confirma y sube en un único commit lógico. No avanzar mientras existan tests rojos, warnings, notices o problemas funcionales.
4. Respeto a la arquitectura OFW: DTO con `ODTO`/`#[ODTOField]`; usuario autenticado obtenido de `LoginMiddleware` y no del cliente; componentes `OComponent` que inyectan servicios con `inject()`; respuestas ordinarias `.json` y componentes anidados de representación/listado; servicios que extienden `OService`.
5. Priorizar ORM `OModel` para operaciones normales; SQL directo solo cuando está justificado por bloqueos, consistencia, agregaciones, filtros u otras limitaciones. Para operaciones atómicas usar la **misma conexión** que utiliza ORM; no alterar OFW para resolver necesidades de Gastos sin estudiar si corresponden al framework.
6. PHP `declare(strict_types=1)`, tipado explícito y PHPDoc para métodos. Proteger datos de espacios y no exponer secretos. Importes **DECIMAL como strings PHP**; **no floats monetarios**.
7. Mantener separados backend ApiGastos y frontend Angular. Mantener esta continuidad actualizada cuando haya avances materiales.

**Aprendizaje de trabajo:** el usuario prefiere un ritmo de entregas completas y verificables, no secuencias de múltiples micro-commits; pide indicaciones precisas y todos los archivos nuevos completos. La reorganización de carpetas se hizo para mejorar la escalabilidad y debe conservarse.

## 3. Entorno y referencias

| Elemento | Referencia vigente |
|---|---|
| Backend | `https://github.com/igorosabel/ApiGastos` |
| Frontend separado | `https://github.com/igorosabel/Gastos` |
| PHP | `>=8.5` |
| OFW | Composer `osumionline/framework: ^9.12.1` |
| Token JWT | `osumionline/plugin-token: ^3.0` |
| Base de datos | MariaDB 12.3.x, InnoDB, utf8mb4 |
| Pruebas | PHPUnit `^13.4`, suites Unit e Integration |
| Despliegue | Debian 13 / Plesk |
| Autoload | PSR-4 `Osumi\\OsumiFramework\\App\\` → `src/` |
| Documentación OFW | `https://github.com/osumionline/framework/tree/main/docs/en` |
| Referencia orientativa adicional | `https://github.com/osumionline/TPV-Backup-API` |

Archivos fuente de normas: `AGENTS.md`, `docs/development/ofw-patterns.md`, `docs/development/testing.md`, `src/Database/001_initial_schema.sql`. `README.md` aporta objetivos generales, pero puede tener un resumen de estado menos actualizado que los endpoints del repositorio.

## 4. Estado funcional por fases

| Fase | Estado al commit de referencia | Alcance |
|---|---|---|
| 0. Base técnica | **HECHA** | Proyecto OFW, configuración, esquema SQL y modelos, pruebas base e integración |
| 1. Autenticación | **HECHA (alcance inicial)** | Registro atómico usuario + espacio personal + membresía, login JWT, `/auth/me` |
| 2. Espacios e invitaciones | **HECHA (alcance inicial)** | Crear/listar espacios, crear/aceptar/listar/revocar invitaciones, control de concurrencia |
| 3. Miembros | **HECHA (alcance actual)** | Listado, salida voluntaria, expulsión, `left_at` y reincorporación; **propiedad/transferencia pendiente de política** |
| 4. Categorías | **HECHA (CRUD básico)** | Crear/listar, editar/ordenar/reorganizar, validar árbol sin ciclos, eliminar hojas sin referencias |
| 5. Movimientos | **EN CURSO** | Creación y listado, filtros y paginación terminados; faltan edición, eliminación/políticas y posibles consultas especializadas |
| 6. Recurrentes y ocurrencias | **PENDIENTE** | Existen tablas/modelos; faltan servicios, endpoints y resolución ACID |
| 7. Informes/previsiones | **PENDIENTE** | Totales mensuales, categorías, comparativas, proyecciones |
| 8. Endurecimiento/documentación/release | **PENDIENTE** | Casos límite, concurrencia, seguridad, rendimiento, contratos y despliegue |
| 9. Evolución posterior | **PENDIENTE/DECISIONES** | Perfiles, transferencias de propiedad, auditoría avanzada, otras funciones futuras |

No atribuir porcentajes globales de avance: las fases restantes son heterogéneas y el núcleo de recurrentes/informes aún no existe como API.

## 5. Contratos HTTP implementados

Todas las rutas siguientes fueron contrastadas con los archivos de `src/Routes` en el commit de referencia. El path de grupo es literal; el comportamiento completo debe contrastarse con DTO, componentes y servicios antes de cambiar un contrato. Salvo registro/login, las rutas enumeradas requieren `LoginMiddleware`.

| Método | Ruta | Estado / detalle |
|---|---|---|
| POST | `/auth/register` | Registro; crea espacio personal y membresía |
| POST | `/auth/login` | Inicio de sesión JWT |
| GET | `/auth/me` | Usuario autenticado |
| GET | `/spaces` | Solo espacios de membresía activa |
| POST | `/spaces` | Crea espacio compartido |
| POST | `/spaces/invitations` | Invitación de propietario |
| POST | `/spaces/invitations/accept` | Aceptación, con reactivación si ya fue miembro inactivo |
| GET | `/spaces/invitations` | Listado de invitaciones por propietario |
| POST | `/spaces/invitations/revoke` | Revocación de pendiente |
| GET | `/spaces/members` | Miembros activos, solo propietario del compartido |
| POST | `/spaces/members/leave` | Salida voluntaria, salvo propietario |
| POST | `/spaces/members/remove` | Expulsión por propietario, salvo a sí mismo |
| GET | `/categories` | Categorías de espacio accesible (`spaceId`) |
| POST | `/categories` | Crear categoría, raíz o hija |
| POST | `/categories/update` | Editar nombre, padre y orden; sin alterar tipo |
| POST | `/categories/delete` | Eliminar si no tiene referencias ni hijos |
| POST | `/transactions` | Crear gasto o ingreso real |
| GET | `/transactions` | Filtros opcionales y paginación |

**`GET /transactions`:** `spaceId` obligatorio; opcionales `dateFrom`, `dateTo` (inclusive, `YYYY-MM-DD`), `type` (`expense`/`income`), `categoryId`, `page` (1 por defecto), `pageSize` (20 por defecto, máximo 100). `page` se limita a 100000 en servicio. Orden `date DESC, id DESC`; respuesta incluye `success`, `transactions`, `total`, `page`, `page_size`, `total_pages`, `error`, `message`. Una página fuera de rango devuelve lista vacía; el conteo y la página son consultas separadas, no una instantánea transaccional garantizada.

**`POST /transactions`:** `spaceId`, `categoryId`, `type`, `amount` string con **exactamente dos decimales** y valor positivo compatible con DECIMAL(12,2), `date` válida `YYYY-MM-DD`, `concept` opcional hasta 255 caracteres. El servicio determina `id_user` a partir de la identidad autenticada. La categoría debe pertenecer al espacio y coincidir con el tipo. Respuesta de creación `201` con representación de movimiento (importe como string).

**Categorías:** `default_type` (`expense` o `income`) inmutable en edición; categorías hija y padre deben ser del mismo tipo y espacio; no se admiten ciclos; la eliminación rechaza dependencias de `category`, `financial_transaction`, `recurring_transaction` y `recurring_occurrence` (`409 category_in_use`). No se efectúan cascadas de borrado de categorías con historial.

## 6. Arquitectura y ubicación del código

```text
src/
  Routes/              Auth.php, Space.php, Category.php, Transaction.php
  DTO/
    Space/             DTO de espacios, miembros e invitaciones
    Category/          Create/Get/Update/DeleteCategoryDTO
    Transaction/       CreateTransactionDTO, GetTransactionsDTO
  Service/
    Auth/              AuthService, RegistrationService, RegistrationValidator
    Space/             SpaceService, SpaceInvitationService, SpaceMemberService
    Category/          CategoryService
    Transaction/       TransactionService
  Module/
    Auth/              Register, Login, Me
    Space/             CreateSpace, GetSpaces, Create/Accept/Get/RevokeInvitation,
                       GetMembers, LeaveSpace, RemoveMember
    Category/          CreateCategory, GetCategories, UpdateCategory, DeleteCategory
    Transaction/       CreateTransaction, GetTransactions
  Component/Model/     Space/List, SpaceInvitation/List, SpaceMember/List,
                       Category/List, Transaction/List
  Model/               User, Space, SpaceMember, SpaceInvitation, Category,
                       FinancialTransaction, RecurringTransaction,
                       RecurringOccurrence
  Database/            001_initial_schema.sql

tests/
  bootstrap.php
  Unit/Auth/ | Unit/Space/ | Unit/Category/ | Unit/Transaction/
  Integration/Auth/ | Integration/Space/ | Integration/Category/ |
  Integration/Transaction/
  Integration/OrmIntegrationTestCase.php
```

**Namespaces DTO:** por ejemplo `Osumi\OsumiFramework\App\DTO\Transaction\GetTransactionsDTO`. Los tests movidos de carpeta conservan sus nombres y namespaces anteriores. Los tests de integración en subcarpetas cargan su base con `require_once dirname(__DIR__) . '/OrmIntegrationTestCase.php';`.

**Estilo HTTP:** el componente toma un DTO, comprueba `isValid()`, invoca servicio inyectado, prepara componentes de respuesta y asigna códigos HTTP. No mezclar validaciones de negocio en DTO ni pasar IDs autenticados controlados por cliente. Las plantillas `.json` usan pipes (`bool`, `plain`, `number`) y componentes de listado.

## 7. Decisiones de negocio y persistencia que deben preservarse

### 7.1 Espacios y membresías

- `space.type` vale `personal` o `shared`; el personal corresponde a su propietario. El propietario debe figurar como miembro.
- `space_member` tiene clave única `(id_space,id_user)` y un campo nullable `left_at`.
- `left_at IS NULL`: membresía **activa**; fecha: membresía histórica/inactiva. No eliminar físicamente la fila para una baja. La FK de `financial_transaction` hacia `(id_space,id_user)` depende de conservar esa historia.
- **Reincorporación acordada: opción A.** Tras nueva invitación, actualizar la **misma fila** `space_member` (`left_at=NULL`, `joined_at=NOW()`), sin insertar otra y manteniendo su ID. No existe tabla de periodos de pertenencia; un historial exhaustivo de entradas/salidas exigiría diseño posterior.
- Un antiguo miembro no puede listar espacios, consultar o alterar categorías/movimientos. Toda nueva operación debe verificar **membresía activa**, nunca solo existencia histórica.
- Solo el propietario administra invitaciones y consulta miembros del compartido; propietario no puede abandonar ni expulsarse. Transferencia de propiedad y destino de espacios sin propietario **no están implementados ni definidos completamente**.

### 7.2 Invitaciones y concurrencia

- Invitar requiere propiedad, email válido, destinatario que no sea miembro activo y ausencia de invitación pendiente vigente duplicada.
- Token aleatorio: se guarda únicamente **SHA-256**; no publicar `token_hash` en listados. La aceptación exige token válido, email del usuario autenticado coincidente, pendiente, no revocada, no caducada; consumir una sola vez y añadir/reactivar miembro atómicamente.
- Se usa bloqueo `SELECT ... FOR UPDATE` sobre el espacio en creación de invitaciones y en operaciones de pertenencia; aceptación también bloquea invitación y espacio. Mantener un **orden de bloqueo consistente** para nuevas operaciones y revisar riesgo de interbloqueos si se añaden flujos concurrentes.
- Existe prueba de contención con dos conexiones; no asumir que equivale a cobertura exhaustiva de todas las carreras posibles.

### 7.3 Categorías

- `category` se particiona por `id_space`, tiene `id_parent`, `name`, `default_type` y `sort_order`.
- Padre e hija mismo espacio y tipo; no auto-padre ni ciclos indirectos. Editar permite cambiar nombre/padre/orden, **no** tipo ni espacio.
- Reorganizaciones usan transacción y bloqueo de espacio, además de recorrido de ascendientes. Crear y borrar categorías de forma concurrente requiere seguir revisando invariantes al ampliar usos.
- Borrar únicamente categorías sin hijos y sin referencias en movimientos, plantillas y ocurrencias. Las FKs `ON DELETE RESTRICT` son la defensa definitiva ante referencias concurrentes.
- La limpieza de tests con árboles debe borrar de **hojas a raíces**, nunca confiar en que un ID mayor sea siempre descendiente tras reorganización.

### 7.4 Movimientos reales

- `financial_transaction` utiliza `amount DECIMAL(12,2)` y PHP `?string $amount` en el modelo. No convertir a `float`; validar escala/formato y valor positivo.
- `type` `expense|income`, `date` válida, `category` existente, mismo espacio y tipo; `concept` opcional; `id_user` siempre autenticado y asociado a membresía activa.
- Para crear movimiento, el servicio bloquea fila `space` dentro de la transacción: coordina creación con `leave/remove`. Tras confirmar una baja no se podrá crear un movimiento como usuario inactivo.
- Para búsquedas filtradas se justifica SQL preparado (`COUNT`, filtros, `LIMIT/OFFSET`, orden estable) mientras que creación/operaciones ordinarias siguen recurriendo a OModel cuando encaja.
- **Punto a decidir antes de editar o eliminar:** quién puede cambiar movimientos anteriores (cualquier miembro activo, solo autor, propietario, etc.) y qué restricciones se aplicarán cuando un movimiento esté vinculado a una `recurring_occurrence` confirmada. No inventar una política tácita.

### 7.5 Modelo relacional de recurrentes, aún sin API

- `recurring_transaction`: plantilla (`day_of_month`, `start_date`, `end_date`, `active`, tipo, categoría, importe decimal, concepto).
- `recurring_occurrence`: una ocurrencia única por `(id_recurring_transaction, period)`, con `period` al primer día de mes; estados `pending`, `confirmed`, `skipped`; instantánea de categoría/tipo/concepto/importe, y posible `id_transaction`.
- Al confirmar: insertar movimiento y actualizar ocurrencia dentro de **una sola transacción ACID**, bloqueo de ocurrencia y verificación de estado; impedir confirmar dos veces. No reescribir ocurrencias confirmadas al cambiar plantilla; respetar fecha de inicio y mes de creación. Validar consistencia de tipo/categoría/espacio en operación, no solo FKs.

## 8. Esquema, migraciones e integridad

La fuente de esquema para instalaciones nuevas es `src/Database/001_initial_schema.sql` (ocho tablas: `user`, `space`, `space_member`, `space_invitation`, `category`, `financial_transaction`, `recurring_transaction`, `recurring_occurrence`). Usa FKs, claves compuestas, `CHECK`, `UNIQUE` y restricciones `RESTRICT` relevantes. **La presencia de modelos de recurrentes no significa implementación de sus endpoints.**

En fase de membresías se añadió `space_member.left_at DATETIME NULL DEFAULT NULL`; para bases existentes se aplicó una migración `ALTER TABLE` y para instalaciones nuevas figura en el esquema inicial. **No se observa en la rama verificada una carpeta SQL de migraciones incrementales versionadas**; no asumir que existe un gestor automático de migraciones de la aplicación. Verificar procedimientos reales antes de cambiar esquema en producción.

La tarea `php of generateModelFromDB` puede regenerar modelos. Revisar diferencias y metadatos de FKs compuestas/`CHECK` antes de aceptarlos; no sobrescribir ajustes sin inspección. `.ofw-migrations.json` pertenece al mecanismo de OFW y no debe darse por equivalente a migraciones propias de Gastos.

**Eliminaciones peligrosas:** `financial_transaction` referencia a `space_member` y `category` con RESTRICT; `recurring_occurrence` referencia a movimientos y categorías. No aplicar borrado físico ni cascadas a historiales sin política explícita de conservación/anonimización.

## 9. Tests y verificación

Comandos del `composer.json` vigente:

```bash
composer test
composer test:integration
composer test:all
```

`phpunit.xml` mantiene suites por directorio, con descubrimiento en subcarpetas. Las clases de integración heredan de `tests/Integration/OrmIntegrationTestCase.php`, que comprueba que la conexión apunta **exactamente a `gastos_test`** antes de configurar ORM.

Variables necesarias para integración: `GASTOS_TEST_DSN`, `GASTOS_TEST_USER`, `GASTOS_TEST_PASSWORD`. La base debe disponer del esquema actualizado. Los tests limpian exclusivamente sus registros, en orden que respete FKs. Nunca probar escrituras sobre una BD de producción.

**Cobertura presente (familias):** servicios de registro/autenticación; DTO/login/middleware; espacios e invitaciones/concurrencia; membresías/abandono/expulsión/reactivación; categorías y jerarquías; movimientos, importes exactos, permisos y filtros/paginación. Los tests de integración de movimientos se encuentran en `tests/Integration/Transaction/TransactionServiceIntegrationTest.php` y los DTO en `tests/Unit/Transaction/TransactionDTOTest.php`.

**Última validación declarada:** el usuario ejecutó y validó tests y pruebas funcionales del bloque de filtros/paginación antes de subir `339284b`. No se dispone en este documento de log CI ni contador consolidado de casos.

**Prácticas esenciales:** test unitario para DTO y lógica aislable; integración para consultas reales/transacciones/FKs/concurrencia cuando proceda; prueba HTTP funcional con JSON y estados esperados; no dar por cerrado bloque con avisos. Los tests previos podrían requerir adaptación al ampliarse el dominio, sin reducir cobertura.

## 10. Historial de hitos desde la versión anterior

| Commit abreviado | Cambio publicado |
|---|---|
| `fc790a9` | Consultar miembros de espacios compartidos |
| `4227407` | Abandonar espacio, expulsar y conservar baja lógica/reactivación |
| `2d6211e` | Crear y listar categorías por espacio |
| `372032b` | Editar y reorganizar categorías sin ciclos |
| `bb81610` | Eliminar categorías sin dependencias |
| `b880f49` | Reorganización de DTO y pruebas por dominios |
| `aced9ee` | Crear y listar ingresos/gastos |
| **`339284b`** | **Filtros y paginación de movimientos; HEAD de referencia** |

Anterior a estos hitos: autenticación, creación/listado de espacios, invitaciones y refuerzo de concurrencia, cuyo último commit de referencia del documento previo era `4376b4e` (`fix(spaces): proteger creación concurrente de invitaciones`).

## 11. Roadmap recomendado a partir de aquí

### Fase 5 — Completar movimientos (prioridad inmediata)

1. **Edición de movimientos:** contrato HTTP y DTO propios en `Transaction/`, servicio, validación de importe/tipo/fecha/categoría, permisos y protección de movimiento vinculado a recurrente. Acordar quién puede editar los movimientos de un espacio compartido y qué datos son inmutables (`id_space`, autor original, vínculo con ocurrencia).
2. **Eliminación/política de conservación:** decidir entre borrado físico permitido para movimientos no vinculados, baja lógica o anulación; las FKs hacia ocurrencias confirmadas impiden borrados triviales. Diseñar prueba de historial y restricciones antes de implementar.
3. **Consultas avanzadas solo si necesarias:** filtros por autor, búsqueda de texto, agregaciones, paginación escalable cuando el volumen real lo requiera. El `GET` actual ya filtra por fechas/tipo/categoría y pagina.

### Fase 6 — Recurrentes y ocurrencias

1. CRUD de plantillas recurrentes; reglas de día 1–31 y meses cortos, fechas/activación y autorización.
2. Generación idempotente de ocurrencias mensuales, unique `(id_recurring_transaction,period)` y conservación de instantáneas.
3. Listar pendientes/confirmadas/omitidas, resolver como confirmado u omitido en transacción; crear movimiento real al confirmar de forma atómica y asegurar doble ejecución/concurrencia.
4. Pruebas complejas de cambios de plantilla, reintentos, fechas límite y permisos.

### Fase 7 — Informes

- Totales de ingresos/gastos por mes, saldo, desglose por categoría, periodos y comparativas.
- Previsiones que distingan transacciones confirmadas y ocurrencias pendientes, sin doble contabilización.
- Importes agregados calculados con `DECIMAL` o aritmética exacta, nunca con `float`.

### Fase 8 — Hardening y documentación

- Repasar permisos sistemáticos y errores HTTP/JSON, límites de consulta, carga, SQL e índices, concurrencia y protección ante carreras.
- Completar ejemplos contractuales en documentación, guía de migraciones y despliegue seguro; verificar README frente a estado real.
- Pruebas end-to-end y estrategia de release/instalación autoalojable.

### Decisiones aún abiertas

- Política fina de edición/autoría de movimientos en espacios compartidos.
- Eliminación/anulación de transacciones y efecto sobre recurrentes confirmados.
- Transferencia de propiedad y posible baja del propietario; no implementada.
- Auditoría de entradas/salidas históricas de miembros: `left_at` reutiliza fila y no mantiene todos los períodos.
- Tratamiento de borrado/anonimización de cuentas y espacios con historial.
- Gestión de perfil/cambio de contraseña y funcionalidades de mantenimiento de usuario.
- Política de acceso a listado de miembros y visibilidad de direcciones de correo (actualmente listado restringido a propietario y sin necesidad de exponer email).

## 12. Protocolo para iniciar una conversación nueva

Copiar este mensaje de arranque y acompañarlo del enlace o archivo:

> Estamos desarrollando **ApiGastos** (backend OFW), repositorio `https://github.com/igorosabel/ApiGastos`. Lee primero el `AGENTS.md` vigente y `docs/development/CONTINUIDAD_APIGASTOS.md`; revisa el HEAD y el código real, no supongas que este documento sigue siendo el último estado. La referencia conocida es el commit `339284b`, con autenticación/espacios/miembros/categorías y creación/listado/filtros/paginación de movimientos implementados. Seguimos con la **edición de movimientos**, pendiente de definir los permisos y la relación con recurrentes confirmados. Quiero bloques completos, incrementales, con **todos los archivos y pruebas**, sin avanzar hasta que valide y suba el commit. Backend y frontend Angular van separados.

**Pasos del asistente al retomar:** comprobar HEAD y commits posteriores; leer `AGENTS.md`, patrones, testing, `TransactionService`, DTO y componentes `Transaction`, SQL y pruebas; comparar lo pendiente con endpoints; discutir solo decisiones realmente no resueltas; preparar un bloque funcional completo con pruebas y verificación manual; esperar validación y commit; actualizar continuidad tras hitos materiales.

---

**Fin de la fotografía — commit `339284b53fa6570b6778002546d5312c57d5c61f`, 15/10/2026.**

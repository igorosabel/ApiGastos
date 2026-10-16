# ApiGastos — Estado de continuidad v1.0.0

**Fecha de revisión:** 16 de octubre de 2026.
**Repositorio:** https://github.com/igorosabel/ApiGastos
**Referencia previa al cierre:** de26e744a3
**Estado:** backend funcionalmente completo para la v1.0.0,
pendiente de la validación final y publicación.

Este documento resume el estado del backend.
La fuente de verdad es siempre el código actual.

Antes de modificar el proyecto, consultar AGENTS.md,
los archivos reales y la documentación de desarrollo.

## Tecnologías

- PHP 8.5+
- Osumi Framework 9.12.1
- MariaDB 12.3.x
- PHPUnit 13
- Debian 13 / Plesk

El frontend Angular se desarrolla en otro repositorio.

## Funcionalidades implementadas

### Autenticación

- Registro de usuarios.
- Creación del espacio personal.
- Login mediante JWT.
- Consulta del usuario autenticado.

### Espacios

- Espacios personales y compartidos.
- Invitaciones, aceptación y revocación.
- Gestión de miembros y bajas lógicas.
- Comprobaciones de pertenencia y autorización.

### Categorías y movimientos

- Categorías jerárquicas por espacio.
- Creación, consulta, edición y eliminación controlada.
- Ingresos y gastos con importes DECIMAL.
- Filtros y paginación de movimientos.
- Protección de movimientos vinculados a recurrentes.

### Recurrentes

- Creación y edición de plantillas.
- Activación y desactivación.
- Generación individual y por espacio.
- Ocurrencias con instantáneas históricas.
- Confirmación y omisión.
- Filtros y paginación.
- Transacciones, bloqueos e idempotencia.

### Informes

- Resumen económico mensual.
- Desglose por categorías.
- Evolución entre meses.
- Previsiones económicas.
- Separación de movimientos reales y pendientes.

Las rutas están definidas en src/Routes/.
Actualmente existen 33 rutas REST principales,
además de la ruta inicial de la aplicación.

## Decisiones importantes

### Datos y arquitectura

- Backend OFW y frontend Angular independientes.
- Identidad autenticada desde LoginMiddleware.
- Permisos comprobados siempre en el servidor.
- Aislamiento por espacio contable.
- Importes DECIMAL representados mediante string.
- No utilizar float para operaciones monetarias.
- No modificar modelos generados sin justificación.

### Recurrentes

- Generación mensual explícita, no automática.
- Una ocurrencia por plantilla y periodo.
- Las ocurrencias existentes son inmutables
  respecto a los cambios posteriores de plantilla.
- Confirmar crea un único movimiento real.
- Omitir no crea movimientos.
- Los estados resueltos son irreversibles.
- La generación masiva no es atómica globalmente.
- Los reintentos son idempotentes.
- La reactivación sigue la política A:
  permite solicitar explícitamente meses pendientes
  que todavía cumplan las reglas temporales.

Consultar docs/api/recurring.md.

### Fechas

- Los periodos y fechas económicas no tienen hora.
- El día nominal se ajusta al último día del mes.
- No se generan meses anteriores al de creación.
- No existe una zona horaria específica por espacio.

### CORS y autenticación

- CORS abierto para la versión 1.0.
- JWT mediante Authorization: Bearer.
- No se utiliza autenticación automática por cookies.
- CORS no sustituye la autorización del servidor.

## Pruebas

Comandos disponibles:

- composer test
- composer test:integration
- composer test:all

La integración utiliza exclusivamente gastos_test.

El desarrollador ha confirmado la ejecución
satisfactoria de las suites en los bloques
anteriores a la preparación de release.

Las comprobaciones HTTP y de despliegue
se realizan por separado.

## Despliegue

Consultar docs/deployment.md.

La instalación requiere configuración privada,
MariaDB, HTTPS y un directorio público correcto.

El esquema inicial está en:

src/Database/001_initial_schema.sql

No existe todavía un sistema de migraciones
incrementales para instalaciones anteriores.

## Fuera del alcance inicial

- Desarrollo y publicación del frontend Angular.
- Gestión avanzada del perfil y contraseña.
- Generación automática programada.
- Transferencia de propiedad de espacios.
- Eliminación o anonimización integral de cuentas.
- Historial completo de activación de plantillas.
- Historial completo de membresías.
- Sistema de migraciones incrementales.

Estos puntos no bloquean por sí mismos la
publicación del alcance definido para ApiGastos 1.0.

## Documentación vigente

- AGENTS.md
- docs/development/ofw-patterns.md
- docs/development/testing.md
- docs/deployment.md
- docs/api/recurring.md
- docs/api/reports.md

La continuidad anterior permanece archivada en
CONTINUIDAD_APIGASTOS_HISTORICO.md.

## Próximo trabajo

Una vez publicada la v1.0.0 del backend,
continuar con el desarrollo independiente
del frontend Angular.

Cualquier defecto detectado durante la integración
se corregirá mediante una nueva versión del backend.

No ampliar la API sin identificar una necesidad
funcional concreta.

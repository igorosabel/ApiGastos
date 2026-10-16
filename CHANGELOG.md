# Changelog

Los cambios relevantes de ApiGastos se documentan
en este archivo.

El versionado sigue Semantic Versioning.

## [1.0.0] - 2026-10-16

Primera versión estable del backend REST de Gastos.

### Funcionalidades

- Registro y autenticación JWT.
- Espacios personales y compartidos.
- Invitaciones y gestión de miembros.
- Categorías jerárquicas.
- Gestión de ingresos y gastos.
- Movimientos recurrentes y ocurrencias.
- Generación individual y masiva.
- Confirmación y omisión de ocurrencias.
- Informes mensuales y por categorías.
- Evolución y previsiones económicas.

### Seguridad e integridad

- Autorización y aislamiento por espacio.
- Importes monetarios con precisión decimal.
- Operaciones atómicas con transacciones.
- Protección frente a duplicados y concurrencia.
- Validación de configuración de producción.
- Protección de archivos ocultos en Apache.
- Gestión de errores de inicialización mediante OFW.

### Comportamiento documentado

- CORS abierto con autenticación JWT.
- Generación recurrente explícita.
- Reintentos idempotentes.
- Ocurrencias históricas conservadas.
- Previsiones separadas de contabilidad real.

### Alcance

Esta versión corresponde únicamente al backend
ApiGastos.

El frontend Angular se publica independientemente.

No incorpora generación automática programada,
gestión avanzada de cuentas ni un sistema de
migraciones incrementales.

# Estrategia de pruebas — ApiGastos

## Objetivo

Comprobar las reglas de negocio, la integración con OFW, la persistencia y los permisos sin depender accidentalmente de la base de datos de producción.

## Pruebas unitarias

Ubicación: `tests/Unit/`.

- Comprobar reglas de validación y comportamiento aislado.
- Usar `createStub()` para configurar comportamientos simulados.
- Usar `createMock()` cuando sea necesario verificar llamadas.
- Comprobar resultados, excepciones y estados HTTP.
- No acceder a MariaDB real desde esta suite.

## Pruebas de componentes

Los componentes de OFW pueden depender de servicios inyectados y del ciclo de vida del framework.

Para probar únicamente su lógica, seguir el patrón contrastado en TPV Backup API: construir el componente sin ejecutar su constructor cuando esté justificado y suministrar las dependencias simuladas.

No utilizar esta técnica como sustituto de las pruebas HTTP.

Para los middlewares y la función global `inject()`, preparar el contexto de OFW que corresponda.

## Pruebas de integración

Ubicación: `tests/Integration/`.

- Utilizar exclusivamente la base de datos `gastos_test`.
- Validar la base de datos activa antes de cualquier operación de escritura.
- Mantener aislamiento entre casos.
- Limpiar los registros creados.
- Probar operaciones del ORM mediante modelos reales.
- Verificar transacciones y rollback.
- Comprobar aislamiento entre usuarios y espacios.
- No mezclar una conexión PDO independiente con otra conexión utilizada por el ORM para verificar una misma transacción.

## Configuración de OFW

La inicialización de `OCore`, `OConfig` y `ODBContainer` debe seguir el patrón validado en `OrmIntegrationTestCase`.

No cargar automáticamente la configuración de producción para ejecutar pruebas de integración.

El bootstrap de PHPUnit debe permitir utilizar las funciones globales necesarias de OFW.

## Pruebas funcionales HTTP

Cuando se añada o modifique un endpoint, comprobar:

- Código HTTP correcto.
- Formato JSON válido.
- Campos y tipos esperados.
- Autenticación cuando corresponda.
- Permisos y aislamiento entre espacios.
- Persistencia y efectos secundarios.
- Respuestas de validación y errores.

## Pruebas de concurrencia con MariaDB

Las pruebas de contención utilizan dos conexiones PDO
independientes contra la base `gastos_test`.

Antes de realizar operaciones se verifica el nombre
de la base de datos de ambas conexiones.

La primera conexión mantiene un bloqueo transaccional
mediante `SELECT ... FOR UPDATE`.

La segunda ejecuta el servicio real usando su propia
conexión y un tiempo de espera InnoDB limitado a un
segundo.

Las pruebas comprueban:

- Que la operación respeta el bloqueo.
- Que el timeout produce una reversión limpia.
- Que no quedan escrituras parciales.
- Que la operación puede reintentarse correctamente.
- Que no se generan ocurrencias ni movimientos duplicados.

Estas pruebas verifican contención real entre dos
sesiones de base de datos, pero no constituyen una
prueba de carga ni simulan dos peticiones HTTP
ejecutándose simultáneamente.

Siempre deben restaurarse las conexiones, transacciones
y el contexto global de OFW mediante `finally`.

El timeout reducido solo se utiliza en la sesión
secundaria de pruebas y no debe aplicarse en producción.

## Comandos

```bash
composer test
composer test:integration
```

Las pruebas deben finalizar sin errores, fallos, warnings ni notices.

## Refactorizaciones

Después de modificar la implementación:

1. Revisar las pruebas afectadas.
2. Retirar simulaciones que ya no representen el código.
3. Sustituirlas por pruebas equivalentes.
4. Mantener o mejorar la cobertura.
5. No cerrar el bloque únicamente porque las pruebas restantes estén en verde.

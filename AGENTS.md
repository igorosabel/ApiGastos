# AGENTS.md — ApiGastos

## 1. Proyecto

API REST de Gastos desarrollada con Osumi Framework.

Tecnologías:
- PHP 8.5+
- Osumi Framework 9.12.1
- MariaDB 12.3.3
- Debian 13 / Plesk
- PHPUnit 13

El backend OFW y el frontend Angular se desarrollan por separado. No mezclar cambios de ambos proyectos en un mismo bloque.

## 2. Fuentes de referencia

Antes de proponer cambios:

1. Leer este archivo.
2. Consultar el código real y actualizado del repositorio.
3. Revisar las convenciones documentadas en `docs/development/`.
4. Consultar la documentación oficial y, cuando sea necesario, el código fuente de OFW.
5. Buscar implementaciones equivalentes ya existentes antes de crear soluciones nuevas.

Referencias:
- OFW: https://github.com/osumionline/framework/tree/main/docs/en
- TPV Backup API: https://github.com/osumionline/TPV-Backup-API

La documentación oficial y el código de la versión utilizada de OFW prevalecen sobre ejemplos de otras aplicaciones.

No asumir que las decisiones de conversaciones anteriores siguen reflejando el estado actual del repositorio.

## 3. Convenciones de OFW

### DTO

- Preferir `ODTO` con atributos `#[ODTOField]` para entradas estructuradas.
- Declarar mediante DTO los campos obligatorios.
- Obtener los identificadores autenticados desde el contexto del middleware, nunca desde parámetros controlados por el cliente.
- Comprobar `isValid()` antes de utilizar los datos.
- Mantener las reglas de negocio en servicios, no en DTO.

### Componentes

- Los componentes de rutas deben extender `OComponent`.
- Inyectar servicios mediante `inject()`; no construirlos directamente.
- Utilizar el componente principalmente para orquestar la petición y preparar la respuesta.
- Preferir plantillas `.json` para respuestas JSON ordinarias.
- Reservar `OStreamResponse` para respuestas que realmente necesiten streaming.
- No serializar manualmente listados mediante `json_encode()` cuando puedan representarse con componentes anidados.
- Reutilizar componentes de representación de modelos y componentes de listado cuando corresponda.

### Servicios

- Extender `OService`.
- Centralizar las operaciones y reglas de negocio.
- No añadir `final` automáticamente.
- Evitar dependencias innecesarias.
- Permitir pruebas aisladas sin introducir mecanismos que compliquen innecesariamente el código de producción.

### ORM

- Priorizar los modelos `OModel` y sus métodos (`where()`, `findOne()`, `save()`, etc.).
- Utilizar SQL directo cuando exista una justificación concreta: consultas complejas, operaciones especiales o limitaciones del ORM.
- No sustituir indiscriminadamente el ORM por PDO.
- Utilizar transacciones para operaciones que deban ser atómicas.
- Comprobar que las operaciones de una misma transacción utilizan la misma conexión.
- No modificar modelos generados manualmente salvo necesidad justificada.

### Middlewares

- Utilizar los middlewares de OFW para autenticación y comprobaciones transversales.
- Proteger las rutas mediante `OMiddleware::PHASE_BEFORE`.
- Publicar identidad y datos confiables mediante el contexto del middleware.
- Aplicar los middlewares únicamente a las rutas que los necesiten.
- No utilizar mecanismos antiguos de OFW si existe una alternativa vigente documentada.

## 4. Seguridad y datos

- Nunca incluir credenciales, claves JWT o secretos en el repositorio.
- No confiar en validaciones realizadas exclusivamente desde Angular.
- Validar permisos y pertenencia a espacios desde la API.
- Nunca confiar en identificadores de usuario proporcionados por el cliente.
- Aislar estrictamente la información de cada espacio.
- Utilizar `DECIMAL` para importes monetarios y representarlos como `string` en PHP.
- No utilizar `float` para cálculos monetarios.
- Proteger con transacciones las operaciones atómicas.
- Evitar exponer detalles internos en respuestas de error.
- No eliminar físicamente datos con historial sin una política explícita.

## 5. Tests

- Crear pruebas para cada funcionalidad.
- Separar tests unitarios y de integración.
- Utilizar `createStub()` cuando únicamente se simule un comportamiento.
- Utilizar `createMock()` cuando se necesiten expectativas verificables.
- Evitar instanciar componentes dependientes de OFW sin preparar su contexto.
- Los componentes pueden probarse con dependencias simuladas siguiendo patrones ya contrastados.
- Probar el ORM y las transacciones mediante integración cuando corresponda.
- No mantener tests que sigan simulando una implementación anterior después de refactorizarla.
- Los tests de integración deben usar exclusivamente `gastos_test`.
- Verificar el destino de la conexión antes de realizar escrituras.
- Limpiar los datos creados durante las pruebas.
- No considerar correcto un bloque si PHPUnit devuelve errores, fallos, warnings o notices.
- No reducir la cobertura existente sin justificarlo y recuperar las pruebas equivalentes.

## 6. Estilo de código

- Utilizar `declare(strict_types=1)` en PHP.
- Todos los métodos deben incluir PHPDoc.
- Utilizar tipos explícitos siempre que sea posible.
- Mantener nombres y convenciones coherentes con el proyecto.
- No añadir saltos de línea innecesarios.
- Separar visualmente únicamente bloques lógicos.
- No añadir abstracciones, capas o dependencias sin una necesidad identificada.
- No modificar OFW para resolver problemas de Gastos sin analizar y discutir previamente si corresponde al framework.

## 7. Flujo de desarrollo

Trabajar mediante bloques pequeños, verificables y funcionalmente completos.

Para cada bloque:

1. Revisar el código y la documentación relevante.
2. Explicar brevemente la funcionalidad, los archivos afectados y las decisiones técnicas.
3. Proponer el código y sus pruebas.
4. Permitir la ejecución local de PHPUnit y las comprobaciones funcionales.
5. Corregir todos los problemas encontrados.
6. Esperar la validación del usuario antes de avanzar al siguiente bloque.
7. Agrupar los cambios relacionados en un commit lógico.

No fragmentar artificialmente el desarrollo en un commit por archivo o por corrección mínima.

No avanzar a funcionalidades nuevas mientras el bloque actual tenga pruebas pendientes, errores o problemas conocidos.

## 8. Criterios para asistentes de IA

- No inventar métodos, convenciones, estructuras ni funcionalidades de OFW.
- Consultar documentación o código fuente antes de utilizar APIs desconocidas.
- Ante una duda arquitectónica que no pueda resolverse con el repositorio o su documentación, preguntar antes de decidir.
- No proponer soluciones improvisadas para hacer pasar tests.
- No modificar código de producción para resolver problemas exclusivos del entorno de pruebas, salvo que exista un defecto real.
- Si una propuesta se aparta de las convenciones establecidas, justificarlo expresamente.
- Priorizar soluciones mantenibles y coherentes sobre soluciones rápidas.
- Respetar las decisiones previamente documentadas.


# AGENTS.md — ApiGastos

## Proyecto
API REST de Gastos desarrollada con Osumi Framework.

## Tecnologías
- PHP 8.5+
- Osumi Framework 9.12.1
- MariaDB 12.3.3
- Debian 13 / Plesk

## Reglas generales
- Consultar el código existente antes de proponer cambios.
- Utilizar las convenciones actuales de OFW.
- Mantener la lógica de negocio en servicios.
- No introducir dependencias innecesarias.
- No modificar los modelos generados sin justificarlo.

## Base de datos
- Utilizar DECIMAL para importes monetarios.
- Representar DECIMAL mediante string en PHP.
- No utilizar float para cálculos monetarios.
- Respetar las restricciones entre espacios.
- Utilizar transacciones en operaciones atómicas.

## Seguridad
- Nunca incluir credenciales en el repositorio.
- Validar siempre los permisos desde la API.
- No confiar únicamente en validaciones del frontend.

## Desarrollo
- Trabajar mediante cambios pequeños.
- Definir pruebas para cada funcionalidad.
- No continuar al siguiente bloque sin validar el actual.
- Ante cualquier duda preguntar antes de inventar nada.
- Cada mensaje tiene que corresponder con una unidad lógica, suficientemente pequeña y auto-contenida.
- No hace falta manejar un archivo por mensaje ni 20 archivos, hay que buscar un equilibrio.

## Código
- Hay que usar tipado estricto en todo el desarrollo.
- Todos los métodos tienen que tener PHPDoc.
- Hay que crear tests unitarios a lo largo de todo el desarrollo.
- No hay que añadir saltos de línea innecesarios, tienen que ser para separar bloques lógicos.

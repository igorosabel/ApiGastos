# ApiGastos

API REST para **Gastos**, una aplicación de código abierto destinada a gestionar ingresos y gastos personales y compartidos.

El objetivo es ofrecer una herramienta ligera, sencilla y autoalojable, sin publicidad, servicios de terceros innecesarios ni explotación comercial de los datos de sus usuarios.

## Funcionalidades

La aplicación está diseñada alrededor del concepto de **espacios**, que permiten organizar la contabilidad de manera independiente.

Cada usuario dispone de un espacio personal y puede crear o participar en espacios compartidos, como una familia, una comunidad de vecinos o una asociación.

Las principales funcionalidades previstas son:

- **Usuarios:** registro, autenticación y gestión de cuentas personales.
- **Espacios:** creación y administración de espacios privados y compartidos, con invitaciones y gestión de miembros.
- **Movimientos:** registro de ingresos y gastos, organizados por fecha, concepto y categoría.
- **Categorías:** árboles jerárquicos de categorías independientes para cada espacio.
- **Recurrentes:** definición de ingresos y gastos periódicos, con movimientos pendientes de confirmación.
- **Informes:** consulta de resultados mensuales, estadísticas por categorías y previsiones de ingresos y gastos.

## Tecnologías

El backend está desarrollado utilizando:

- **PHP 8.5+**
- **Osumi Framework 9.12.1+**
- **MariaDB 12.3**
- **Composer**

El entorno de producción utiliza Debian 13 y Plesk.

## Arquitectura

ApiGastos proporciona los servicios de negocio y acceso a datos mediante una API REST.

El frontend se desarrolla de forma independiente con Angular y PrimeNG.

Repositorio del frontend: [Gastos](https://github.com/igorosabel/Gastos).

### Modelo de datos

La aplicación utiliza las siguientes entidades principales:

| Entidad | Descripción |
|---|---|
| `User` | Usuarios registrados |
| `Space` | Espacios contables personales y compartidos |
| `SpaceMember` | Miembros de cada espacio |
| `SpaceInvitation` | Invitaciones a espacios |
| `Category` | Categorías jerárquicas |
| `FinancialTransaction` | Movimientos reales de ingresos y gastos |
| `RecurringTransaction` | Plantillas de movimientos recurrentes |
| `RecurringOccurrence` | Ocurrencias pendientes, confirmadas u omitidas |

Los datos están aislados por espacio y todas las operaciones deben validar los permisos del usuario autenticado.

Los importes monetarios utilizan `DECIMAL` en MariaDB y se representan mediante cadenas de texto en PHP para evitar pérdidas de precisión.

## Instalación y configuración

### Requisitos

- PHP 8.5 o superior.
- Composer.
- MariaDB compatible con el esquema del proyecto.
- Servidor web compatible con Osumi Framework.

### Dependencias

Clonar el repositorio e instalar las dependencias:

```bash
git clone https://github.com/igorosabel/ApiGastos.git
cd ApiGastos
composer install
```

### Configuración

Crear el archivo de configuración a partir del ejemplo:

```bash
cp src/Config/Config.json.example src/Config/Config.json
```

Editar `src/Config/Config.json` con los parámetros del entorno, especialmente la conexión a la base de datos y la URL de la API.

Este archivo contiene configuración privada y no debe incluirse en el control de versiones.

### Base de datos

Crear la base de datos MariaDB e importar el esquema SQL correspondiente a la versión de la aplicación.

Una vez configurada la conexión, los modelos ORM pueden generarse mediante:

```bash
php of generateModelFromDB
```

Los modelos existentes en el repositorio pueden incluir ajustes propios de la aplicación, por lo que no deben sobrescribirse sin revisar previamente los cambios generados.

## Estado del proyecto

**En desarrollo.**

El esquema inicial de la base de datos y los modelos ORM están preparados. Los servicios de autenticación, gestión de espacios, movimientos, recurrentes e informes se implementarán progresivamente.

La aplicación todavía no dispone de una versión estable.

## Desarrollo

Las convenciones de programación y las instrucciones para trabajar en el proyecto se encuentran en [AGENTS.md](AGENTS.md).

El desarrollo se realiza de forma incremental, con cambios pequeños y verificables, priorizando la seguridad, la integridad de los datos y la sencillez de mantenimiento.

## Licencia

Este proyecto se distribuye bajo la [licencia MIT](LICENSE).

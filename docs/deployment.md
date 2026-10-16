# Despliegue de ApiGastos

## Estado

ApiGastos está en desarrollo y todavía no dispone
de una versión estable publicada.

Este documento describe los requisitos y pasos de
instalación del backend OFW.

El frontend Angular se despliega por separado.

## Requisitos

- PHP 8.5 o superior.
- Composer.
- MariaDB 12.3.x con tablas InnoDB.
- Extensiones PHP requeridas por Composer y OFW,
  incluyendo PDO MySQL y mbstring.
- Apache con mod_rewrite, o una configuración
  equivalente que enrute hacia `public/index.php`.
- HTTPS configurado para el dominio de la API.

En Plesk, comprobar la versión PHP asignada al
dominio y su Document Root.

## Directorio público

El punto de entrada es:

`public/index.php`

El Document Root del dominio debe apuntar a
`public/`, no a la raíz del repositorio.

Los directorios `src/`, `tests/` y los archivos
de configuración privados no deben ser
accesibles directamente desde la web.

## Instalación de dependencias

Clonar el repositorio e instalar las dependencias:

```bash
composer install --no-dev --optimize-autoloader
```

Las dependencias de desarrollo no son necesarias
para servir la API.

## Configuración privada

Copiar el archivo de ejemplo:

```bash
cp src/Config/Config.json.example src/Config/Config.json
```

El archivo resultante no debe incluirse en Git.

Configurar al menos:

- `db.host`: servidor MariaDB.
- `db.user`: usuario de base de datos.
- `db.pass`: contraseña de base de datos.
- `db.name`: nombre de la base.
- `base_url`: URL HTTPS pública de esta instalación.
- `extra.jwt_secret`: secreto de autenticación.

No utilizar los valores de ejemplo como credenciales
o secretos reales.

### Secreto JWT

Generar un secreto criptográficamente aleatorio:

```bash
php -r 'echo bin2hex(random_bytes(32)), PHP_EOL;'
```

Copiar el valor resultante en `extra.jwt_secret`.

El servicio de autenticación requiere un secreto
de al menos 32 caracteres.

Utilizar un secreto diferente por instalación.
No publicarlo ni incluirlo en logs o documentación.

Cambiar el secreto invalida los JWT anteriores.

## Base de datos

Crear una base MariaDB con el charset y la
configuración apropiados para el esquema.

Para una instalación nueva sobre una base vacía,
importar:

`src/Database/001_initial_schema.sql`

El SQL inicial no es un mecanismo de migraciones.
No ejecutarlo de nuevo indiscriminadamente
sobre una instalación con datos.

Los modelos ORM ya están incluidos en el repositorio.

No es necesario ejecutar `generateModelFromDB`
durante el despliegue ordinario.

## Apache y HTTPS

La configuración principal de reescritura está en:

`public/.htaccess`

Conserva la cabecera Authorization y dirige las
rutas de la API a `index.php`.

La redirección existente de www a no-www
utiliza HTTPS.

La redirección general de HTTP a HTTPS debe
configurarse en el servidor web o en Plesk,
evitando bucles con posibles proxies inversos.

No considerar segura una instalación que permita
enviar contraseñas o JWT sobre HTTP.

## Configuración específica de Plesk

Los archivos de `public/` pueden contener
ajustes asociados al alojamiento utilizado
durante el desarrollo.

En particular, revisar `public/.php-ini`,
que contiene una ruta específica de servidor.

No asumir que dicha ruta existe en instalaciones
externas.

La configuración PHP efectiva, los certificados
TLS y las redirecciones deben verificarse
en el dominio desplegado.

## Comprobación funcional

Antes de dar por válida una instalación:

1. Comprobar que el dominio sirve la API por HTTPS.
2. Comprobar que las rutas llegan a `public/index.php`.
3. Comprobar que GET /auth/me sin token
   devuelve HTTP 401.
4. Comprobar que un usuario válido puede iniciar sesión.
5. Comprobar que Authorization: Bearer llega correctamente
   al middleware de autenticación.
6. Verificar que un usuario autenticado solo accede
   a sus espacios autorizados.
7. Comprobar que los errores no revelan credenciales,
   secretos o trazas internas.

Si el dominio www está configurado, verificar que
su redirección conserva el esquema HTTPS.

## Pruebas y entorno de integración

Los tests de integración deben ejecutarse únicamente
contra `gastos_test`.

Las variables utilizadas son:

- `GASTOS_TEST_DSN`
- `GASTOS_TEST_USER`
- `GASTOS_TEST_PASSWORD`

La clase `OrmIntegrationTestCase` verifica el nombre
de la base antes de ejecutar operaciones.

Nunca ejecutar la suite de integración utilizando
credenciales o una base de datos de producción.

## Antes de publicar una release

- Confirmar que todas las pruebas pasan.
- Verificar configuración privada y secretos.
- Revisar Document Root y HTTPS.
- Comprobar permisos y aislamiento de espacios.
- Preparar una copia de seguridad de los datos.
- Verificar el procedimiento de recuperación.
- Documentar cualquier modificación del esquema.
- Revisar las diferencias entre la instalación
  existente y la versión que se publicará.

No considerar el esquema inicial como una herramienta
de actualización de instalaciones existentes.

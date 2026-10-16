# ApiGastos — Guía de endpoints REST

**Versión de referencia:** `v1.0.0` (release del 16 de octubre de 2026).  **Destino propuesto:** `docs/api/endpoints.md`.

Esta guía describe los **33 endpoints REST** de ApiGastos y está pensada como índice de integración para el frontend Angular. Los nombres, tipos y estructuras se han contrastado con las rutas, DTO, componentes y plantillas JSON de la release. Los identificadores, fechas, importes y correos de los ejemplos son **ficticios**; no se trata de datos de una instalación real.

## Convenciones generales

- **URL base de ejemplo:** `https://apigastos.osumi.es/`. En una instalación autoalojada se sustituye por el valor público configurado en `base_url`. Las rutas que figuran en los títulos **no incluyen parámetros de consulta**.
- **Autenticación:** todas las rutas salvo `POST /auth/register` y `POST /auth/login` necesitan `Authorization: Bearer <jwt>`. La identidad del usuario procede del middleware: **no se envía `idUser`**.
- **Peticiones GET:** los parámetros indicados se envían en el **query string**; para respetar el índice de rutas limpias, se muestran por separado como un objeto JSON ilustrativo. No se envía cuerpo JSON para estos GET.
- **Peticiones POST:** se muestra un cuerpo JSON (`Content-Type: application/json`); OFW 9.12.1 admite objetos JSON como cuerpo de solicitud.
- **Tipos:** `int` = número entero; `boolean` = booleano JSON; importes = **cadenas decimales con dos posiciones** (no números de punto flotante). Las entradas usan `camelCase` y los campos de respuesta habitualmente `snake_case`.
- **Fechas:** operaciones `YYYY-MM-DD`; informes `YYYY-MM`; periodos recurrentes `YYYY-MM-01`; las marcas temporales de invitación o resolución suelen ser cadenas SQL `YYYY-MM-DD HH:mm:ss`, mientras `expires_at` de login es Unix timestamp numérico.
- **Resultados:** las respuestas de éxito se documentan completas, con la forma de sus propiedades, incluidos los campos `null`. Las fechas y valores son ejemplos. Los errores controlados suelen devolver `success: false`, `error` y `message` (ver apartado final).

## Índice

- [Autenticación](#autenticacion)
- [Espacios e invitaciones](#espacios-e-invitaciones)
- [Categorías](#categorias)
- [Movimientos](#movimientos)
- [Recurrentes y ocurrencias](#recurrentes-y-ocurrencias)
- [Informes y previsiones](#informes-y-previsiones)
- [Errores HTTP y consideraciones de integración](#errores-http-y-consideraciones-de-integracion)

## Resumen de rutas

| Método | Ruta | Descripción |
|---|---|---|
| `POST` | `/auth/register` | Registra un usuario y crea su espacio personal. |
| `POST` | `/auth/login` | Comprueba las credenciales y entrega un JWT válido durante 24 horas. |
| `GET` | `/auth/me` | Devuelve los datos del usuario autenticado. |
| `GET` | `/spaces` | Obtiene los espacios personales y compartidos a los que pertenece el usuario autenticado. |
| `POST` | `/spaces` | Crea un espacio compartido; el usuario pasa a ser su propietario. |
| `POST` | `/spaces/invitations` | Crea una invitación para incorporar una persona a un espacio compartido. |
| `POST` | `/spaces/invitations/accept` | Acepta una invitación utilizando su token; incorpora o reactiva al usuario en el espacio. |
| `GET` | `/spaces/invitations` | Lista las invitaciones de un espacio compartido. |
| `POST` | `/spaces/invitations/revoke` | Revoca una invitación que continúa pendiente. |
| `GET` | `/spaces/members` | Obtiene los miembros activos de un espacio al que tiene acceso. |
| `POST` | `/spaces/members/leave` | Abandona un espacio compartido; conserva el historial de membresía. |
| `POST` | `/spaces/members/remove` | Expulsa a un miembro de un espacio compartido. |
| `GET` | `/categories` | Lista las categorías del espacio, con su estructura jerárquica expresada mediante `parent_id`. |
| `POST` | `/categories` | Crea una categoría principal o subordinada a otra. |
| `POST` | `/categories/update` | Modifica el nombre, padre o posición de una categoría. |
| `POST` | `/categories/delete` | Elimina una categoría cuando no tiene dependencias que lo impidan. |
| `GET` | `/transactions` | Busca movimientos económicos reales, con filtros y paginación. |
| `POST` | `/transactions` | Registra un ingreso o gasto real. |
| `POST` | `/transactions/update` | Actualiza un movimiento independiente existente. |
| `POST` | `/transactions/delete` | Borra un movimiento independiente. |
| `GET` | `/recurring` | Lista plantillas recurrentes activas e inactivas de un espacio. |
| `POST` | `/recurring` | Crea una plantilla mensual recurrente activa. |
| `POST` | `/recurring/update` | Actualiza los datos de una plantilla; no altera las ocurrencias históricas existentes. |
| `POST` | `/recurring/status` | Activa o desactiva una plantilla recurrente. |
| `POST` | `/recurring/generate` | Genera una ocurrencia mensual o devuelve una ya existente. |
| `POST` | `/recurring/generate-space` | Genera un periodo para las plantillas inicialmente activas del espacio. |
| `GET` | `/recurring/occurrences` | Filtra y pagina ocurrencias de un espacio. |
| `POST` | `/recurring/occurrences/resolve` | Confirma u omite una ocurrencia que todavía esté pendiente. |
| `GET` | `/reports/monthly` | Resume ingresos, gastos, balance y recuentos reales de un mes. |
| `GET` | `/reports/monthly/categories` | Desglosa importes y número de movimientos reales por categoría. |
| `GET` | `/reports/evolution` | Devuelve los resúmenes reales de cada mes en un intervalo. |
| `GET` | `/reports/monthly/forecast` | Combina contabilidad real y ocurrencias pendientes para estimar un mes. |
| `GET` | `/reports/evolution/forecast` | Devuelve previsiones mensuales para cada mes de un intervalo. |

## Autenticación

### `POST /auth/register`

Registra un usuario y crea su espacio personal.

**Parámetros de entrada**

| Parámetro | Tipo | Obligatorio | Descripción |
|---|---|:---:|---|
| `name` | string | Sí | Nombre del usuario. |
| `email` | string | Sí | Correo electrónico de acceso. |
| `password` | string | Sí | Contraseña de la cuenta. |

**Ejemplo de cuerpo JSON (POST)**

```json
{
  "name": "Ana López",
  "email": "ana@example.com",
  "password": "ClaveSegura!2026"
}
```

**Ejemplo de respuesta correcta — HTTP 201**

```json
{
  "success": true,
  "user_id": 1,
  "space_id": 11,
  "error": null,
  "message": null
}
```

**Notas:** Ruta pública. `409` si el correo ya está registrado. El `space_id` identifica el espacio personal creado durante el registro.

### `POST /auth/login`

Comprueba las credenciales y entrega un JWT válido durante 24 horas.

**Parámetros de entrada**

| Parámetro | Tipo | Obligatorio | Descripción |
|---|---|:---:|---|
| `email` | string | Sí | Correo electrónico. |
| `password` | string | Sí | Contraseña. |

**Ejemplo de cuerpo JSON (POST)**

```json
{
  "email": "ana@example.com",
  "password": "ClaveSegura!2026"
}
```

**Ejemplo de respuesta correcta — HTTP 200**

```json
{
  "success": true,
  "token": "eyJhbGciOiJIUzI1NiJ9.ejemplo.firma",
  "expires_at": 1792252800,
  "user": {
    "id": 1,
    "name": "Ana López",
    "email": "ana@example.com"
  },
  "error": null,
  "message": null
}
```

**Notas:** Ruta pública. `expires_at` es un timestamp Unix expresado como número. El token mostrado es ilustrativo, no un JWT válido. `401` para credenciales incorrectas.

### `GET /auth/me`

Devuelve los datos del usuario autenticado.

**Parámetros de entrada**

Ninguno (además de la cabecera de autenticación cuando proceda).

**Ejemplo de respuesta correcta — HTTP 200**

```json
{
  "success": true,
  "user": {
    "id": 1,
    "name": "Ana López",
    "email": "ana@example.com"
  }
}
```

**Notas:** Necesita `Authorization: Bearer <token>`. La identidad no se envía como parámetro.

## Espacios e invitaciones

### `GET /spaces`

Obtiene los espacios personales y compartidos a los que pertenece el usuario autenticado.

**Parámetros de entrada**

Ninguno (además de la cabecera de autenticación cuando proceda).

**Ejemplo de respuesta correcta — HTTP 200**

```json
{
  "success": true,
  "spaces": [
    {
      "id": 11,
      "name": "Personal",
      "type": "personal",
      "owner_id": 1,
      "is_owner": true
    },
    {
      "id": 12,
      "name": "Familia",
      "type": "shared",
      "owner_id": 1,
      "is_owner": true
    }
  ]
}
```

**Notas:** `is_owner` indica si el usuario autenticado es propietario del espacio.

### `POST /spaces`

Crea un espacio compartido; el usuario pasa a ser su propietario.

**Parámetros de entrada**

| Parámetro | Tipo | Obligatorio | Descripción |
|---|---|:---:|---|
| `name` | string | Sí | Nombre del nuevo espacio. |

**Ejemplo de cuerpo JSON (POST)**

```json
{
  "name": "Familia"
}
```

**Ejemplo de respuesta correcta — HTTP 201**

```json
{
  "success": true,
  "space": {
    "id": 12,
    "name": "Familia",
    "type": "shared",
    "owner_id": 1,
    "is_owner": true
  },
  "error": null,
  "message": null
}
```

### `POST /spaces/invitations`

Crea una invitación para incorporar una persona a un espacio compartido.

**Parámetros de entrada**

| Parámetro | Tipo | Obligatorio | Descripción |
|---|---|:---:|---|
| `spaceId` | int | Sí | Espacio compartido; debe administrarlo su propietario. |
| `email` | string | Sí | Correo destinatario. |

**Ejemplo de cuerpo JSON (POST)**

```json
{
  "spaceId": 12,
  "email": "pepe@example.com"
}
```

**Ejemplo de respuesta correcta — HTTP 201**

```json
{
  "success": true,
  "invitation": {
    "id": 61,
    "space_id": 12,
    "email": "pepe@example.com",
    "token": "aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa",
    "expires_at": "2026-10-23 13:00:00"
  },
  "error": null,
  "message": null
}
```

**Notas:** El token de invitación se entrega **solo al crearla** y se necesita para aceptarla; no aparece en los listados. Caduca a los 7 días. `409` para conflictos (p. ej., invitación pendiente duplicada).

### `POST /spaces/invitations/accept`

Acepta una invitación utilizando su token; incorpora o reactiva al usuario en el espacio.

**Parámetros de entrada**

| Parámetro | Tipo | Obligatorio | Descripción |
|---|---|:---:|---|
| `token` | string | Sí | Token de invitación en formato hexadecimal de 64 caracteres. |

**Ejemplo de cuerpo JSON (POST)**

```json
{
  "token": "aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa"
}
```

**Ejemplo de respuesta correcta — HTTP 200**

```json
{
  "success": true,
  "space": {
    "id": 12,
    "name": "Familia",
    "type": "shared",
    "owner_id": 1,
    "is_owner": false
  },
  "error": null,
  "message": null
}
```

**Notas:** El token debe estar pendiente, vigente y dirigido al correo del usuario autenticado. `409` ante invitaciones ya consumidas u otros conflictos.

### `GET /spaces/invitations`

Lista las invitaciones de un espacio compartido.

**Parámetros de entrada**

| Parámetro | Tipo | Obligatorio | Descripción |
|---|---|:---:|---|
| `spaceId` | int | Sí | Identificador del espacio. |

**Ejemplo de parámetros de consulta (GET)**

```json
{
  "spaceId": 12
}
```

**Ejemplo de respuesta correcta — HTTP 200**

```json
{
  "success": true,
  "invitations": [
    {
      "id": 61,
      "space_id": 12,
      "email": "pepe@example.com",
      "invited_by": 1,
      "expires_at": "2026-10-23 13:00:00",
      "accepted_at": null,
      "revoked_at": null,
      "status": "pending"
    }
  ],
  "error": null,
  "message": null
}
```

**Notas:** Solo el propietario del espacio puede administrarlas. Estados posibles: `pending`, `accepted`, `revoked`, `expired`. Nunca se devuelve el token original.

### `POST /spaces/invitations/revoke`

Revoca una invitación que continúa pendiente.

**Parámetros de entrada**

| Parámetro | Tipo | Obligatorio | Descripción |
|---|---|:---:|---|
| `invitationId` | int | Sí | Identificador de la invitación. |

**Ejemplo de cuerpo JSON (POST)**

```json
{
  "invitationId": 61
}
```

**Ejemplo de respuesta correcta — HTTP 200**

```json
{
  "success": true,
  "invitation": {
    "id": 61,
    "space_id": 12,
    "email": "pepe@example.com",
    "invited_by": 1,
    "expires_at": "2026-10-23 13:00:00",
    "accepted_at": null,
    "revoked_at": "2026-10-16 13:10:00",
    "status": "revoked"
  },
  "error": null,
  "message": null
}
```

**Notas:** Solo el propietario. `409` si ya se aceptó, revocó o caducó.

### `GET /spaces/members`

Obtiene los miembros activos de un espacio al que tiene acceso.

**Parámetros de entrada**

| Parámetro | Tipo | Obligatorio | Descripción |
|---|---|:---:|---|
| `spaceId` | int | Sí | Identificador del espacio. |

**Ejemplo de parámetros de consulta (GET)**

```json
{
  "spaceId": 12
}
```

**Ejemplo de respuesta correcta — HTTP 200**

```json
{
  "success": true,
  "members": [
    {
      "id": 1,
      "name": "Ana López",
      "joined_at": "2026-10-01 09:00:00",
      "is_owner": true
    },
    {
      "id": 2,
      "name": "Pepe",
      "joined_at": "2026-10-16 13:15:00",
      "is_owner": false
    }
  ],
  "error": null,
  "message": null
}
```

**Notas:** El campo `id` de cada miembro es el **identificador del usuario**, no un identificador de la fila de membresía.

### `POST /spaces/members/leave`

Abandona un espacio compartido; conserva el historial de membresía.

**Parámetros de entrada**

| Parámetro | Tipo | Obligatorio | Descripción |
|---|---|:---:|---|
| `spaceId` | int | Sí | Espacio que abandona el usuario autenticado. |

**Ejemplo de cuerpo JSON (POST)**

```json
{
  "spaceId": 12
}
```

**Ejemplo de respuesta correcta — HTTP 200**

```json
{
  "success": true,
  "error": null,
  "message": null
}
```

**Notas:** El propietario no puede abandonar su propio espacio compartido sin una transferencia de propiedad (no implementada). `409` cuando la baja es incompatible con las reglas del espacio.

### `POST /spaces/members/remove`

Expulsa a un miembro de un espacio compartido.

**Parámetros de entrada**

| Parámetro | Tipo | Obligatorio | Descripción |
|---|---|:---:|---|
| `spaceId` | int | Sí | Identificador del espacio. |
| `userId` | int | Sí | Identificador del miembro que se va a expulsar. |

**Ejemplo de cuerpo JSON (POST)**

```json
{
  "spaceId": 12,
  "userId": 2
}
```

**Ejemplo de respuesta correcta — HTTP 200**

```json
{
  "success": true,
  "error": null,
  "message": null
}
```

**Notas:** Solo el propietario. `userId` es el **usuario objetivo**, distinto del usuario autenticado. `409` para conflictos de membresía.

## Categorías

### `GET /categories`

Lista las categorías del espacio, con su estructura jerárquica expresada mediante `parent_id`.

**Parámetros de entrada**

| Parámetro | Tipo | Obligatorio | Descripción |
|---|---|:---:|---|
| `spaceId` | int | Sí | Espacio del que se consultan las categorías. |

**Ejemplo de parámetros de consulta (GET)**

```json
{
  "spaceId": 12
}
```

**Ejemplo de respuesta correcta — HTTP 200**

```json
{
  "success": true,
  "categories": [
    {
      "id": 7,
      "space_id": 12,
      "parent_id": null,
      "name": "Hogar",
      "default_type": "expense",
      "sort_order": 0
    },
    {
      "id": 8,
      "space_id": 12,
      "parent_id": 7,
      "name": "Suministros",
      "default_type": "expense",
      "sort_order": 1
    }
  ],
  "error": null,
  "message": null
}
```

**Notas:** La respuesta es una lista plana: Angular puede construir el árbol usando `parent_id`.

### `POST /categories`

Crea una categoría principal o subordinada a otra.

**Parámetros de entrada**

| Parámetro | Tipo | Obligatorio | Descripción |
|---|---|:---:|---|
| `spaceId` | int | Sí | Espacio contable. |
| `name` | string | Sí | Nombre de la categoría. |
| `defaultType` | string | Sí | `income` o `expense`. |
| `parentId` | int | null | No | Categoría padre; `null` para raíz. |
| `sortOrder` | int | No | Orden dentro del nivel; por defecto 0. |

**Ejemplo de cuerpo JSON (POST)**

```json
{
  "spaceId": 12,
  "name": "Hogar",
  "defaultType": "expense",
  "parentId": null,
  "sortOrder": 0
}
```

**Ejemplo de respuesta correcta — HTTP 201**

```json
{
  "success": true,
  "category": {
    "id": 7,
    "space_id": 12,
    "parent_id": null,
    "name": "Hogar",
    "default_type": "expense",
    "sort_order": 0
  },
  "error": null,
  "message": null
}
```

**Notas:** Una categoría hija debe pertenecer al mismo espacio y respetar las reglas del tipo.

### `POST /categories/update`

Modifica el nombre, padre o posición de una categoría.

**Parámetros de entrada**

| Parámetro | Tipo | Obligatorio | Descripción |
|---|---|:---:|---|
| `spaceId` | int | Sí | Espacio contable. |
| `categoryId` | int | Sí | Categoría a modificar. |
| `name` | string | Sí | Nuevo nombre. |
| `parentId` | int | null | No | Nuevo padre o `null` para categoría raíz. |
| `sortOrder` | int | Sí | Nuevo orden. |

**Ejemplo de cuerpo JSON (POST)**

```json
{
  "spaceId": 12,
  "categoryId": 7,
  "name": "Casa",
  "parentId": null,
  "sortOrder": 0
}
```

**Ejemplo de respuesta correcta — HTTP 200**

```json
{
  "success": true,
  "category": {
    "id": 7,
    "space_id": 12,
    "parent_id": null,
    "name": "Casa",
    "default_type": "expense",
    "sort_order": 0
  },
  "error": null,
  "message": null
}
```

**Notas:** El tipo `default_type` no se cambia aquí. No se permite crear ciclos en el árbol.

### `POST /categories/delete`

Elimina una categoría cuando no tiene dependencias que lo impidan.

**Parámetros de entrada**

| Parámetro | Tipo | Obligatorio | Descripción |
|---|---|:---:|---|
| `spaceId` | int | Sí | Espacio contable. |
| `categoryId` | int | Sí | Categoría que se solicita eliminar. |

**Ejemplo de cuerpo JSON (POST)**

```json
{
  "spaceId": 12,
  "categoryId": 8
}
```

**Ejemplo de respuesta correcta — HTTP 200**

```json
{
  "success": true,
  "error": null,
  "message": null
}
```

**Notas:** `409` (`category_in_use`) si la categoría tiene dependencias o no puede borrarse. No es un borrado en cascada de movimientos.

## Movimientos

### `GET /transactions`

Busca movimientos económicos reales, con filtros y paginación.

**Parámetros de entrada**

| Parámetro | Tipo | Obligatorio | Descripción |
|---|---|:---:|---|
| `spaceId` | int | Sí | Espacio contable. |
| `dateFrom` | string | No | Primera fecha incluida, `YYYY-MM-DD`. |
| `dateTo` | string | No | Última fecha incluida, `YYYY-MM-DD`. |
| `type` | string | No | `income` o `expense`. |
| `categoryId` | int | No | Categoría exacta por la que filtrar. |
| `page` | int | No | Página desde 1; por defecto 1. |
| `pageSize` | int | No | Resultados por página; por defecto 20, máximo 100. |

**Ejemplo de parámetros de consulta (GET)**

```json
{
  "spaceId": 12,
  "dateFrom": "2026-10-01",
  "dateTo": "2026-10-31",
  "type": "expense",
  "page": 1,
  "pageSize": 20
}
```

**Ejemplo de respuesta correcta — HTTP 200**

```json
{
  "success": true,
  "transactions": [
    {
      "id": 31,
      "space_id": 12,
      "user_id": 1,
      "category_id": 7,
      "type": "expense",
      "amount": "95.40",
      "concept": "Electricidad",
      "date": "2026-10-20"
    }
  ],
  "total": 1,
  "page": 1,
  "page_size": 20,
  "total_pages": 1,
  "error": null,
  "message": null
}
```

**Notas:** Orden: `date DESC, id DESC`. Los importes monetarios son cadenas. Los límites de página se validan en el servicio.

### `POST /transactions`

Registra un ingreso o gasto real.

**Parámetros de entrada**

| Parámetro | Tipo | Obligatorio | Descripción |
|---|---|:---:|---|
| `spaceId` | int | Sí | Espacio contable. |
| `categoryId` | int | Sí | Categoría compatible con el tipo. |
| `type` | string | Sí | `income` o `expense`. |
| `amount` | string decimal | Sí | Importe positivo con dos decimales, p. ej. `"95.40"`. |
| `concept` | string | null | No | Descripción, hasta 255 caracteres. |
| `date` | string | Sí | Fecha económica `YYYY-MM-DD`. |

**Ejemplo de cuerpo JSON (POST)**

```json
{
  "spaceId": 12,
  "categoryId": 7,
  "type": "expense",
  "amount": "95.40",
  "concept": "Electricidad",
  "date": "2026-10-20"
}
```

**Ejemplo de respuesta correcta — HTTP 201**

```json
{
  "success": true,
  "transaction": {
    "id": 31,
    "space_id": 12,
    "user_id": 1,
    "category_id": 7,
    "type": "expense",
    "amount": "95.40",
    "concept": "Electricidad",
    "date": "2026-10-20"
  },
  "error": null,
  "message": null
}
```

### `POST /transactions/update`

Actualiza un movimiento independiente existente.

**Parámetros de entrada**

| Parámetro | Tipo | Obligatorio | Descripción |
|---|---|:---:|---|
| `spaceId` | int | Sí | Espacio contable. |
| `transactionId` | int | Sí | Movimiento a editar. |
| `categoryId` | int | Sí | Categoría nueva o conservada. |
| `type` | string | Sí | `income` o `expense`. |
| `amount` | string decimal | Sí | Importe positivo, dos decimales. |
| `concept` | string | null | No | Nuevo concepto. |
| `date` | string | Sí | Fecha económica `YYYY-MM-DD`. |

**Ejemplo de cuerpo JSON (POST)**

```json
{
  "spaceId": 12,
  "transactionId": 31,
  "categoryId": 7,
  "type": "expense",
  "amount": "98.00",
  "concept": "Electricidad ajustada",
  "date": "2026-10-20"
}
```

**Ejemplo de respuesta correcta — HTTP 200**

```json
{
  "success": true,
  "transaction": {
    "id": 31,
    "space_id": 12,
    "user_id": 1,
    "category_id": 7,
    "type": "expense",
    "amount": "98.00",
    "concept": "Electricidad ajustada",
    "date": "2026-10-20"
  },
  "error": null,
  "message": null
}
```

**Notas:** No se permite editar un movimiento vinculado a una ocurrencia recurrente (`409`, `transaction_conflict`).

### `POST /transactions/delete`

Borra un movimiento independiente.

**Parámetros de entrada**

| Parámetro | Tipo | Obligatorio | Descripción |
|---|---|:---:|---|
| `spaceId` | int | Sí | Espacio contable. |
| `transactionId` | int | Sí | Movimiento a eliminar. |

**Ejemplo de cuerpo JSON (POST)**

```json
{
  "spaceId": 12,
  "transactionId": 31
}
```

**Ejemplo de respuesta correcta — HTTP 200**

```json
{
  "success": true,
  "error": null,
  "message": null
}
```

**Notas:** La eliminación es definitiva para movimientos independientes. Las operaciones sobre movimientos recurrentes vinculados se rechazan con `409`.

## Recurrentes y ocurrencias

### `GET /recurring`

Lista plantillas recurrentes activas e inactivas de un espacio.

**Parámetros de entrada**

| Parámetro | Tipo | Obligatorio | Descripción |
|---|---|:---:|---|
| `spaceId` | int | Sí | Espacio contable. |

**Ejemplo de parámetros de consulta (GET)**

```json
{
  "spaceId": 12
}
```

**Ejemplo de respuesta correcta — HTTP 200**

```json
{
  "success": true,
  "recurring": [
    {
      "id": 50,
      "space_id": 12,
      "category_id": 7,
      "type": "expense",
      "amount": "95.40",
      "concept": "Electricidad",
      "day_of_month": 20,
      "start_date": "2026-10-01",
      "end_date": null,
      "active": true
    }
  ],
  "error": null,
  "message": null
}
```

**Notas:** No aplica paginación en v1.0.0.

### `POST /recurring`

Crea una plantilla mensual recurrente activa.

**Parámetros de entrada**

| Parámetro | Tipo | Obligatorio | Descripción |
|---|---|:---:|---|
| `spaceId` | int | Sí | Espacio contable. |
| `categoryId` | int | Sí | Categoría compatible con el tipo. |
| `type` | string | Sí | `income` o `expense`. |
| `amount` | string decimal | Sí | Importe positivo, dos decimales. |
| `concept` | string | Sí | Concepto de la plantilla. |
| `dayOfMonth` | int | Sí | Día nominal entre 1 y 31. |
| `startDate` | string | Sí | Fecha inicial `YYYY-MM-DD`. |
| `endDate` | string | null | No | Fecha final `YYYY-MM-DD` o `null`. |

**Ejemplo de cuerpo JSON (POST)**

```json
{
  "spaceId": 12,
  "categoryId": 7,
  "type": "expense",
  "amount": "95.40",
  "concept": "Electricidad",
  "dayOfMonth": 20,
  "startDate": "2026-10-01",
  "endDate": null
}
```

**Ejemplo de respuesta correcta — HTTP 201**

```json
{
  "success": true,
  "recurring": {
    "id": 50,
    "space_id": 12,
    "category_id": 7,
    "type": "expense",
    "amount": "95.40",
    "concept": "Electricidad",
    "day_of_month": 20,
    "start_date": "2026-10-01",
    "end_date": null,
    "active": true
  },
  "error": null,
  "message": null
}
```

**Notas:** No incluye un campo de entrada `active`: las nuevas plantillas se crean activas.

### `POST /recurring/update`

Actualiza los datos de una plantilla; no altera las ocurrencias históricas existentes.

**Parámetros de entrada**

| Parámetro | Tipo | Obligatorio | Descripción |
|---|---|:---:|---|
| `spaceId` | int | Sí | Espacio contable. |
| `recurringId` | int | Sí | Plantilla que se modifica. |
| `categoryId` | int | Sí | Categoría compatible con el tipo. |
| `type` | string | Sí | `income` o `expense`. |
| `amount` | string decimal | Sí | Importe positivo, dos decimales. |
| `concept` | string | Sí | Concepto nuevo. |
| `dayOfMonth` | int | Sí | Día entre 1 y 31. |
| `startDate` | string | Sí | Fecha inicial `YYYY-MM-DD`. |
| `endDate` | string | null | No | Fecha final opcional. |

**Ejemplo de cuerpo JSON (POST)**

```json
{
  "spaceId": 12,
  "recurringId": 50,
  "categoryId": 7,
  "type": "expense",
  "amount": "105.00",
  "concept": "Electricidad nueva",
  "dayOfMonth": 20,
  "startDate": "2026-10-01",
  "endDate": null
}
```

**Ejemplo de respuesta correcta — HTTP 200**

```json
{
  "success": true,
  "recurring": {
    "id": 50,
    "space_id": 12,
    "category_id": 7,
    "type": "expense",
    "amount": "105.00",
    "concept": "Electricidad nueva",
    "day_of_month": 20,
    "start_date": "2026-10-01",
    "end_date": null,
    "active": true
  },
  "error": null,
  "message": null
}
```

### `POST /recurring/status`

Activa o desactiva una plantilla recurrente.

**Parámetros de entrada**

| Parámetro | Tipo | Obligatorio | Descripción |
|---|---|:---:|---|
| `spaceId` | int | Sí | Espacio contable. |
| `recurringId` | int | Sí | Plantilla que se modifica. |
| `active` | boolean | Sí | `true` para activar; `false` para desactivar. |

**Ejemplo de cuerpo JSON (POST)**

```json
{
  "spaceId": 12,
  "recurringId": 50,
  "active": false
}
```

**Ejemplo de respuesta correcta — HTTP 200**

```json
{
  "success": true,
  "recurring": {
    "id": 50,
    "space_id": 12,
    "category_id": 7,
    "type": "expense",
    "amount": "95.40",
    "concept": "Electricidad",
    "day_of_month": 20,
    "start_date": "2026-10-01",
    "end_date": null,
    "active": false
  },
  "error": null,
  "message": null
}
```

**Notas:** Las ocurrencias ya existentes no se eliminan ni se reabren. La reactivación puede permitir generar explícitamente periodos válidos no creados.

### `POST /recurring/generate`

Genera una ocurrencia mensual o devuelve una ya existente.

**Parámetros de entrada**

| Parámetro | Tipo | Obligatorio | Descripción |
|---|---|:---:|---|
| `spaceId` | int | Sí | Espacio contable. |
| `recurringId` | int | Sí | Plantilla recurrente. |
| `period` | string | Sí | Primer día del mes: `YYYY-MM-01`. |

**Ejemplo de cuerpo JSON (POST)**

```json
{
  "spaceId": 12,
  "recurringId": 50,
  "period": "2026-10-01"
}
```

**Ejemplo de respuesta correcta — HTTP 201**

```json
{
  "success": true,
  "created": true,
  "occurrence": {
    "id": 80,
    "space_id": 12,
    "recurring_id": 50,
    "period": "2026-10-01",
    "scheduled_date": "2026-10-20",
    "status": "pending",
    "category_id": 7,
    "type": "expense",
    "concept": "Electricidad",
    "amount": "95.40",
    "transaction_id": null,
    "resolved_at": null
  },
  "error": null,
  "message": null
}
```

**Notas:** Primera generación: `201` y `created: true`. Si ya existe: `200` y `created: false` (misma ocurrencia, aunque la plantilla se haya modificado/desactivado). Fechas sujetas a inicio, fin y mes de creación.

### `POST /recurring/generate-space`

Genera un periodo para las plantillas inicialmente activas del espacio.

**Parámetros de entrada**

| Parámetro | Tipo | Obligatorio | Descripción |
|---|---|:---:|---|
| `spaceId` | int | Sí | Espacio contable. |
| `period` | string | Sí | Primer día del mes: `YYYY-MM-01`. |

**Ejemplo de cuerpo JSON (POST)**

```json
{
  "spaceId": 12,
  "period": "2026-10-01"
}
```

**Ejemplo de respuesta correcta — HTTP 200**

```json
{
  "success": true,
  "created": 1,
  "existing": 0,
  "skipped": 1,
  "total": 2,
  "error": null,
  "message": null
}
```

**Notas:** `created`: nuevas; `existing`: ya generadas; `skipped`: plantillas inicialmente activas no elegibles; `total`: inicialmente activas evaluadas. No es una transacción global: un fallo intermedio puede dejar algunas ocurrencias creadas. Reintentar es idempotente.

### `GET /recurring/occurrences`

Filtra y pagina ocurrencias de un espacio.

**Parámetros de entrada**

| Parámetro | Tipo | Obligatorio | Descripción |
|---|---|:---:|---|
| `spaceId` | int | Sí | Espacio contable. |
| `status` | string | No | `pending`, `confirmed` o `skipped`. |
| `periodFrom` | string | No | Primer periodo incluido `YYYY-MM-01`. |
| `periodTo` | string | No | Último periodo incluido `YYYY-MM-01`. |
| `recurringId` | int | No | Identificador de la plantilla. |
| `page` | int | No | Página; por defecto 1. |
| `pageSize` | int | No | Tamaño; por defecto 20, máximo 100. |

**Ejemplo de parámetros de consulta (GET)**

```json
{
  "spaceId": 12,
  "status": "pending",
  "periodFrom": "2026-10-01",
  "periodTo": "2026-10-01",
  "page": 1,
  "pageSize": 20
}
```

**Ejemplo de respuesta correcta — HTTP 200**

```json
{
  "success": true,
  "occurrences": [
    {
      "id": 80,
      "space_id": 12,
      "recurring_id": 50,
      "period": "2026-10-01",
      "scheduled_date": "2026-10-20",
      "status": "pending",
      "category_id": 7,
      "type": "expense",
      "concept": "Electricidad",
      "amount": "95.40",
      "transaction_id": null,
      "resolved_at": null
    }
  ],
  "total": 1,
  "page": 1,
  "page_size": 20,
  "total_pages": 1,
  "error": null,
  "message": null
}
```

**Notas:** El rango filtra por **periodo**, no por `scheduled_date`. Orden: `scheduled_date DESC, id DESC`.

### `POST /recurring/occurrences/resolve`

Confirma u omite una ocurrencia que todavía esté pendiente.

**Parámetros de entrada**

| Parámetro | Tipo | Obligatorio | Descripción |
|---|---|:---:|---|
| `spaceId` | int | Sí | Espacio contable. |
| `occurrenceId` | int | Sí | Ocurrencia a resolver. |
| `action` | string | Sí | `confirm` o `skip`. |

**Ejemplo de cuerpo JSON (POST)**

```json
{
  "spaceId": 12,
  "occurrenceId": 80,
  "action": "confirm"
}
```

**Ejemplo de respuesta correcta — HTTP 200**

```json
{
  "success": true,
  "occurrence": {
    "id": 80,
    "space_id": 12,
    "recurring_id": 50,
    "period": "2026-10-01",
    "scheduled_date": "2026-10-20",
    "status": "confirmed",
    "category_id": 7,
    "type": "expense",
    "concept": "Electricidad",
    "amount": "95.40",
    "transaction_id": 31,
    "resolved_at": "2026-10-16 13:20:00"
  },
  "error": null,
  "message": null
}
```

**Notas:** `confirm` crea un movimiento real exactamente una vez; `skip` establece estado `skipped` y deja `transaction_id: null`. Un segundo intento de resolver una ocurrencia ya resuelta devuelve `409` (`occurrence_conflict`).

## Informes y previsiones

### `GET /reports/monthly`

Resume ingresos, gastos, balance y recuentos reales de un mes.

**Parámetros de entrada**

| Parámetro | Tipo | Obligatorio | Descripción |
|---|---|:---:|---|
| `spaceId` | int | Sí | Espacio contable. |
| `month` | string | Sí | Mes `YYYY-MM`. |

**Ejemplo de parámetros de consulta (GET)**

```json
{
  "spaceId": 12,
  "month": "2026-10"
}
```

**Ejemplo de respuesta correcta — HTTP 200**

```json
{
  "success": true,
  "report": {
    "space_id": 12,
    "month": "2026-10",
    "income": "2500.00",
    "expense": "1800.00",
    "balance": "700.00",
    "income_count": 1,
    "expense_count": 10,
    "transaction_count": 11
  },
  "error": null,
  "message": null
}
```

**Notas:** Solo suma `financial_transaction` según su fecha económica. Un mes sin movimientos devuelve importes `"0.00"` y contadores cero.

### `GET /reports/monthly/categories`

Desglosa importes y número de movimientos reales por categoría.

**Parámetros de entrada**

| Parámetro | Tipo | Obligatorio | Descripción |
|---|---|:---:|---|
| `spaceId` | int | Sí | Espacio contable. |
| `month` | string | Sí | Mes `YYYY-MM`. |

**Ejemplo de parámetros de consulta (GET)**

```json
{
  "spaceId": 12,
  "month": "2026-10"
}
```

**Ejemplo de respuesta correcta — HTTP 200**

```json
{
  "success": true,
  "report": {
    "space_id": 12,
    "month": "2026-10",
    "categories": [
      {
        "category_id": 7,
        "parent_id": null,
        "name": "Hogar",
        "type": "expense",
        "amount": "425.60",
        "transaction_count": 12
      }
    ]
  },
  "error": null,
  "message": null
}
```

**Notas:** Cada importe agrupa solo los movimientos asignados **directamente** a esa categoría. No suma automáticamente los de categorías hijas. Categorías sin movimientos no aparecen.

### `GET /reports/evolution`

Devuelve los resúmenes reales de cada mes en un intervalo.

**Parámetros de entrada**

| Parámetro | Tipo | Obligatorio | Descripción |
|---|---|:---:|---|
| `spaceId` | int | Sí | Espacio contable. |
| `monthFrom` | string | Sí | Mes inicial `YYYY-MM`. |
| `monthTo` | string | Sí | Mes final `YYYY-MM`. |

**Ejemplo de parámetros de consulta (GET)**

```json
{
  "spaceId": 12,
  "monthFrom": "2026-09",
  "monthTo": "2026-10"
}
```

**Ejemplo de respuesta correcta — HTTP 200**

```json
{
  "success": true,
  "report": {
    "space_id": 12,
    "month_from": "2026-09",
    "month_to": "2026-10",
    "months": [
      {
        "space_id": 12,
        "month": "2026-09",
        "income": "0.00",
        "expense": "0.00",
        "balance": "0.00",
        "income_count": 0,
        "expense_count": 0,
        "transaction_count": 0
      },
      {
        "space_id": 12,
        "month": "2026-10",
        "income": "2500.00",
        "expense": "1800.00",
        "balance": "700.00",
        "income_count": 1,
        "expense_count": 10,
        "transaction_count": 11
      }
    ]
  },
  "error": null,
  "message": null
}
```

**Notas:** El intervalo es inclusivo y está limitado a 24 meses. Incluye meses sin movimientos. Los balances son mensuales, no acumulados.

### `GET /reports/monthly/forecast`

Combina contabilidad real y ocurrencias pendientes para estimar un mes.

**Parámetros de entrada**

| Parámetro | Tipo | Obligatorio | Descripción |
|---|---|:---:|---|
| `spaceId` | int | Sí | Espacio contable. |
| `month` | string | Sí | Mes `YYYY-MM`. |

**Ejemplo de parámetros de consulta (GET)**

```json
{
  "spaceId": 12,
  "month": "2026-10"
}
```

**Ejemplo de respuesta correcta — HTTP 200**

```json
{
  "success": true,
  "report": {
    "space_id": 12,
    "month": "2026-10",
    "actual": {
      "space_id": 12,
      "month": "2026-10",
      "income": "2500.00",
      "expense": "1800.00",
      "balance": "700.00",
      "income_count": 1,
      "expense_count": 10,
      "transaction_count": 11
    },
    "pending": {
      "income": "0.00",
      "expense": "200.00",
      "balance": "-200.00",
      "income_count": 0,
      "expense_count": 1,
      "occurrence_count": 1
    },
    "projected": {
      "income": "2500.00",
      "expense": "2000.00",
      "balance": "500.00"
    }
  },
  "error": null,
  "message": null
}
```

**Notas:** `actual` incluye solo movimientos reales; `pending` agrega las ocurrencias ya generadas y sin resolver; `projected = actual + pending`. No genera automáticamente las ocurrencias ni duplica las confirmadas.

### `GET /reports/evolution/forecast`

Devuelve previsiones mensuales para cada mes de un intervalo.

**Parámetros de entrada**

| Parámetro | Tipo | Obligatorio | Descripción |
|---|---|:---:|---|
| `spaceId` | int | Sí | Espacio contable. |
| `monthFrom` | string | Sí | Mes inicial `YYYY-MM`. |
| `monthTo` | string | Sí | Mes final `YYYY-MM`. |

**Ejemplo de parámetros de consulta (GET)**

```json
{
  "spaceId": 12,
  "monthFrom": "2026-09",
  "monthTo": "2026-10"
}
```

**Ejemplo de respuesta correcta — HTTP 200**

```json
{
  "success": true,
  "report": {
    "space_id": 12,
    "month_from": "2026-09",
    "month_to": "2026-10",
    "months": [
      {
        "space_id": 12,
        "month": "2026-09",
        "actual": {
          "space_id": 12,
          "month": "2026-09",
          "income": "0.00",
          "expense": "0.00",
          "balance": "0.00",
          "income_count": 0,
          "expense_count": 0,
          "transaction_count": 0
        },
        "pending": {
          "income": "0.00",
          "expense": "0.00",
          "balance": "0.00",
          "income_count": 0,
          "expense_count": 0,
          "occurrence_count": 0
        },
        "projected": {
          "income": "0.00",
          "expense": "0.00",
          "balance": "0.00"
        }
      },
      {
        "space_id": 12,
        "month": "2026-10",
        "actual": {
          "space_id": 12,
          "month": "2026-10",
          "income": "2500.00",
          "expense": "1800.00",
          "balance": "700.00",
          "income_count": 1,
          "expense_count": 10,
          "transaction_count": 11
        },
        "pending": {
          "income": "0.00",
          "expense": "200.00",
          "balance": "-200.00",
          "income_count": 0,
          "expense_count": 1,
          "occurrence_count": 1
        },
        "projected": {
          "income": "2500.00",
          "expense": "2000.00",
          "balance": "500.00"
        }
      }
    ]
  },
  "error": null,
  "message": null
}
```

**Notas:** El rango es inclusivo y máximo 24 meses. Incluye meses vacíos. Es una operación de solo lectura; no crea ni confirma ocurrencias.

## Errores HTTP y consideraciones de integración

| HTTP | Motivo común |
|---|---|
| `200` | Operación correcta o consulta completada. |
| `201` | Recurso creado (registro, espacio, invitación, categoría, movimiento, plantilla u ocurrencia nueva). |
| `400` | Datos ausentes o inválidos (`validation_error`). |
| `401` | Credenciales incorrectas en login o JWT ausente, incorrecto o caducado. |
| `403` | Falta de permisos o membresía activa (`forbidden`). |
| `404` | Ruta no encontrada; puede devolver la página de error propia de OFW. |
| `405` | Método HTTP no admitido por la ruta. |
| `409` | Conflictos de reglas de negocio: invitación, membresía, categoría en uso, movimiento vinculado o ocurrencia ya resuelta. |
| `500` | Error inesperado del servidor; **no se garantiza** formato JSON. |

Ejemplo genérico de un error controlado de un componente:

```json
{
  "success": false,
  "error": "validation_error",
  "message": "Faltan datos obligatorios."
}
```

**Importante:** según la plantilla del endpoint, el JSON de error puede incorporar también una propiedad de recurso con `null`, una lista vacía o contadores por defecto. Los errores generados directamente por OFW (por ejemplo `401`, `404`, `405` o `500`) no necesariamente utilizan este envoltorio. En Angular hay que comprobar **primero el estado HTTP y `Content-Type`**, antes de intentar interpretar el cuerpo como JSON.

**Invitaciones:** el token no se obtiene mediante `GET /spaces/invitations`, únicamente en la respuesta de creación, y debe tratarse como credencial temporal.

**Recurrentes:** `POST /recurring/generate-space` no realiza una transacción global; si falla a mitad, pueden permanecer ocurrencias ya generadas. El reintento es idempotente por plantilla y periodo. Las previsiones solo consideran ocurrencias `pending` ya generadas; no lanzan generación automática.

**CORS:** en `v1.0.0` se utiliza la política abierta de OFW (`Access-Control-Allow-Origin: *`) y autenticación por JWT en cabecera Bearer, sin cookies de autenticación automáticas.

**Documentación complementaria:** [`recurring.md`](recurring.md), [`reports.md`](reports.md) y [`../deployment.md`](../deployment.md).

---

**Fuentes revisadas:** etiqueta `v1.0.0` del repositorio `igorosabel/ApiGastos`: `src/Routes/`, `src/DTO/`, `src/Module/`, `src/Component/Model/`, `docs/api/recurring.md`, `docs/api/reports.md`, y el manejo de peticiones JSON de OFW 9.12.1.

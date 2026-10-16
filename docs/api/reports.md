# ApiGastos — Contratos REST de informes

## Alcance

Documentación de los endpoints de informes económicos.

Los informes son operaciones de solo lectura y están
protegidos mediante `LoginMiddleware`.

Base de las rutas: `/reports`.

Todos requieren:

- Cabecera `Authorization: Bearer <token>`.
- `spaceId`: entero positivo de un espacio accesible.
- Membresía activa en el espacio solicitado.

La identidad del usuario procede del middleware,
nunca del parámetro `idUser` de la petición.

## Endpoints

| Método | Ruta | Parámetros adicionales |
|---|---|---|
| GET | `/monthly` | `month` |
| GET | `/monthly/categories` | `month` |
| GET | `/evolution` | `monthFrom`, `monthTo` |
| GET | `/monthly/forecast` | `month` |
| GET | `/evolution/forecast` | `monthFrom`, `monthTo` |

Los meses utilizan el formato `YYYY-MM`.

Los rangos incluyen ambos extremos, deben estar
ordenados y tienen un máximo de 24 meses.

## Estructura HTTP común

Una petición válida devuelve HTTP 200:

```json
{
  "success": true,
  "report": {},
  "error": null,
  "message": null
}
```

Los errores controlados de los componentes usan:

- HTTP 400: `validation_error`, para campos
  obligatorios ausentes o valores no válidos.
- HTTP 403: `forbidden`, cuando no existe una
  membresía activa para el espacio.
- HTTP 401: autenticación rechazada por el
  middleware antes de ejecutar el componente.

En respuestas controladas con error,
`success` es `false` y no se genera un informe.

La respuesta concreta del middleware 401 no tiene
por qué utilizar el mismo envoltorio JSON que
los componentes de informes.

## 1. Resumen mensual

`GET /reports/monthly?spaceId=12&month=2026-10`

`report` contiene:

```json
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
```

Los importes son cadenas decimales con dos
decimales. Los contadores son números enteros.

`balance` = ingresos reales - gastos reales.

Los datos proceden exclusivamente de
`financial_transaction`, filtrando por su
fecha contable `date`.

Un mes sin movimientos devuelve importes
`"0.00"` y contadores `0`.

## 2. Desglose mensual por categoría

`GET /reports/monthly/categories?spaceId=12&month=2026-10`

El objeto `report` contiene `space_id`, `month`
y un array `categories`.

Cada entrada de `categories` tiene:

```json
{
  "category_id": 8,
  "parent_id": null,
  "name": "Alimentación",
  "type": "expense",
  "amount": "425.60",
  "transaction_count": 12
}
```

Los importes corresponden únicamente a movimientos
asignados directamente a cada categoría.

No se acumulan importes de subcategorías en
sus categorías padre.

Las categorías sin movimientos en el mes
no aparecen en el resultado.

El tipo es `income` o `expense`.

## 3. Evolución mensual real

`GET /reports/evolution?spaceId=12&monthFrom=2026-01&monthTo=2026-12`

El objeto `report` contiene:

- `space_id`
- `month_from`
- `month_to`
- `months`: array cronológico de resúmenes mensuales.

Cada elemento de `months` tiene exactamente
los campos del informe `/reports/monthly`.

Los meses sin movimientos también aparecen,
con todos sus importes y contadores a cero.

Los importes de cada mes son independientes:
no representan saldos acumulados.

## 4. Previsión económica mensual

`GET /reports/monthly/forecast?spaceId=12&month=2026-10`

El objeto `report` contiene:

```json
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
```

`actual` es idéntico al resumen económico real.

`pending` agrega exclusivamente las ocurrencias
ya generadas cuyo estado es `pending`.

Las ocurrencias confirmadas figuran en `actual`
a través de sus movimientos reales y no se
vuelven a sumar en `pending`.

Las ocurrencias omitidas no se contabilizan.

`projected` suma importes reales y pendientes,
sin utilizar aritmética de coma flotante.

La previsión asume que todas las pendientes
se confirmarán, pero no garantiza que ocurra.

La consulta no genera ocurrencias automáticamente.
Para generarlas se utiliza por separado
`POST /recurring/generate-space`.

## 5. Evolución de previsiones

`GET /reports/evolution/forecast?spaceId=12&monthFrom=2026-01&monthTo=2026-12`

El objeto `report` contiene:

- `space_id`
- `month_from`
- `month_to`
- `months`: array cronológico de previsiones.

Cada elemento de `months` tiene el contrato
completo del informe `/reports/monthly/forecast`.

Los meses vacíos aparecen igualmente.

Los datos proyectados representan resultados
mensuales estimados, no saldos bancarios.

Consultar este endpoint no genera, confirma
ni omite ocurrencias.

## Reglas monetarias

- Base de datos: `DECIMAL`.
- API: importes representados como `string`.
- Dos posiciones decimales.
- Nunca utilizar `float` para realizar sumas
  de importes de negocio.
- El saldo o balance puede ser negativo.
- Las cantidades deben conservar su precisión
  al llegar al frontend.

En Angular conviene mantener los importes como
cadenas hasta utilizar una estrategia explícita
de aritmética decimal o cantidades en céntimos.

## Seguridad y aislamiento

Los servicios comprueban la membresía activa
antes de consultar los datos económicos.

Un usuario no puede consultar información
de espacios ajenos o espacios que haya abandonado.

Todas las consultas deben seguir acotadas
por `id_space`.

## Pruebas de regresión

La cobertura relevante se encuentra en:

- `tests/Unit/Report/`
- `tests/Integration/Report/`

Verificaciones obligatorias:

1. Exactitud de ingresos, gastos y balances.
2. Separación entre contabilidad real y pendiente.
3. Ausencia de doble contabilización.
4. Meses vacíos y límites de rango.
5. Autenticación y permisos.
6. Aislamiento entre espacios.
7. Ausencia de escrituras en endpoints GET.
8. Tipos JSON y respuestas HTTP correctos.

La documentación debe revisarse cuando se
modifiquen los contratos de estos endpoints.

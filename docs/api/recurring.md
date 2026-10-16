# ApiGastos — Ciclo de vida de los recurrentes

## Alcance

Este documento describe las reglas actuales de
activación, desactivación y generación de
mensualidades recurrentes.

No define una programación automática ni un
historial de pausas que todavía no existen.

## Plantillas recurrentes

Una plantilla puede estar activa o inactiva.

La operación `POST /recurring/status` cambia
su estado, pero no elimina las ocurrencias
creadas previamente.

La edición de una plantilla tampoco modifica
las instantáneas de ocurrencias existentes.

## Generación individual

`POST /recurring/generate`

Requiere `spaceId`, `recurringId` y `period`
en formato `YYYY-MM-01`.

Para una ocurrencia nueva:

- La plantilla debe estar activa.
- El periodo no puede preceder al mes en
  el que se creó la plantilla.
- Debe respetar `startDate` y `endDate`.
- El día nominal se ajusta al último día
  disponible cuando el mes es más corto.

Si ya existe una ocurrencia para la
plantilla y el periodo, se devuelve sin
modificarla, incluso si la plantilla está
desactivada o sus fechas han cambiado.

La restricción única de base de datos
impide duplicar una plantilla y periodo.

## Generación por espacio

`POST /recurring/generate-space`

Evalúa exclusivamente las plantillas que
están activas al obtener el listado inicial.

Los contadores devueltos son:

- `created`: ocurrencias nuevas.
- `existing`: ocurrencias encontradas.
- `skipped`: plantillas inicialmente activas que no permiten crear una nueva ocurrencia por sus fechas o porque fueron desactivadas antes de procesarse.
- `total`: plantillas activas evaluadas.

Una plantilla inactiva no aparece en
estos contadores.

Cada plantilla se procesa de forma
independiente; el lote completo no es
una única transacción.

## Fallos parciales y reintentos

La generación por espacio no es una operación
atómica para todo el lote.

Cada plantilla se procesa en su propia transacción.
Una ocurrencia creada correctamente queda guardada
aunque falle el procesamiento de una plantilla posterior.

Los errores operativos, como un timeout de bloqueo
de MariaDB, no se contabilizan como `skipped`.
Se propagan y provocan el fallo de la petición.

Si el lote falla, no se devuelve una respuesta de
éxito con contadores parciales. Sin embargo, las
ocurrencias confirmadas anteriormente pueden
permanecer almacenadas.

### Recuperación

El cliente puede reintentar la misma petición:

`POST /recurring/generate-space`

Con los mismos `spaceId` y `period`.

Durante el reintento:

- Las ocurrencias ya existentes se reutilizan.
- Las que faltan pueden generarse si las plantillas
  siguen activas y sus fechas continúan siendo válidas.
- Las ocurrencias confirmadas u omitidas no se reabren.
- No se generan duplicados para la misma plantilla
  y periodo.
- El nuevo resultado refleja la situación actual,
  no los contadores del intento que falló.

La API no realiza automáticamente reintentos de
todo el lote.

Antes de implementar reintentos automáticos desde
Angular, será necesario definir cuándo reintentar,
cómo comunicar los errores persistentes y cómo
evitar solicitudes simultáneas innecesarias.

### Garantía

El comportamiento garantizado es idempotencia
por plantilla y periodo, no atomicidad global.

Un fallo de una plantilla no revierte las
mensualidades previamente confirmadas por otras
transacciones.

## Desactivación y reactivación

Mientras una plantilla está inactiva:

- No permite generar nuevas ocurrencias.
- Sus ocurrencias existentes se conservan.
- Sus pendientes pueden resolverse mediante
  el endpoint de confirmación u omisión.
- La generación masiva no la evalúa.

Cuando se reactiva:

- Vuelve a permitir la generación de
  periodos válidos.
- Las ocurrencias existentes se reutilizan.
- No se reabren ocurrencias confirmadas
  ni omitidas.
- Los nuevos periodos utilizan los valores
  actuales de la plantilla.
- Los periodos que quedaron sin generar
  durante la pausa pueden generarse mediante
  una petición explícita si siguen siendo
  válidos conforme a los límites temporales.

**Limitación conocida:** actualmente no existe
un historial de intervalos de activación.

Por ello, la API no puede distinguir entre
un periodo sin generar por una pausa y otro
que simplemente nunca se procesó.

No se debe presentar la reactivación como
una recuperación automática de mensualidades:
hace falta solicitar su generación.

## Relación con los informes

Las ocurrencias pendientes solo se utilizan
en los informes de previsiones.

Una confirmación genera un movimiento real.
La ocurrencia confirmada deja de considerarse
pendiente y su movimiento se incluye en los
informes económicos reales.

Una ocurrencia omitida no genera movimiento
ni contribuye a las previsiones.

Consultar informes no crea ocurrencias.

## Política de reactivación acordada

**Decisión definitiva para la primera versión: opción A.**

Desactivar una plantilla impide generar mensualidades
nuevas mientras permanezca inactiva.

Al reactivarla, vuelven a poder generarse los periodos
válidos que todavía no tengan una ocurrencia, incluidos
aquellos en los que la plantilla estuvo desactivada.

La recuperación requiere una solicitud explícita de
generación. Reactivar una plantilla nunca crea
mensualidades automáticamente.

La generación respeta siempre:

- El mes original de creación de la plantilla.
- La fecha inicial y la fecha final vigentes.
- La unicidad por plantilla y periodo.
- La conservación de las instantáneas históricas.
- La irreversibilidad de los estados confirmed y skipped.

Esta política no requiere almacenar un historial
de periodos de activación y desactivación.

Cualquier cambio posterior de esta regla deberá
tratarse como una decisión de producto.

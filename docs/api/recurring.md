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
- `skipped`: plantillas activas fuera de
  los límites temporales aplicables.
- `total`: plantillas activas evaluadas.

Una plantilla inactiva no aparece en
estos contadores.

Cada plantilla se procesa de forma
independiente; el lote completo no es
una única transacción.

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

## Decisión pendiente

Antes de implementar automatismos o una
política estricta de pausas, decidir si:

1. Reactivar permite recuperar cualquier
   mensualidad válida que falte, como ahora.
2. Los meses en pausa deben quedar excluidos
   definitivamente.
3. Debe permitirse recuperar periodos
   anteriores únicamente mediante una acción
   explícita de usuario.

Las opciones 2 y 3 pueden requerir almacenar
historial de activación o decisiones por periodo.

No cambiar esta política implícitamente
como parte de una optimización técnica.

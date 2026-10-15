<?php declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Service\Recurring;

use DateTimeImmutable;
use DomainException;
use InvalidArgumentException;
use Osumi\OsumiFramework\App\Model\RecurringOccurrence;
use Osumi\OsumiFramework\Core\OService;
use Osumi\OsumiFramework\ORM\ODB;
use PDO;
use RuntimeException;
use Throwable;

class OccurrenceService extends OService {
    /**
     * Genera una mensualidad o devuelve la ya existente.
     *
     * @param int $space_id Espacio contable.
     * @param int $user_id Miembro autenticado.
     * @param int $recurring_id Plantilla recurrente.
     * @param string $period Primer día del mes YYYY-MM-01.
     * @return array{occurrence: RecurringOccurrence, created: bool}
     */
    public function generate(
        int $space_id,
        int $user_id,
        int $recurring_id,
        string $period
    ): array {
        if (
            $space_id <= 0 ||
            $user_id <= 0 ||
            $recurring_id <= 0
        ) {
            throw new InvalidArgumentException(
                'Los identificadores no son válidos.'
            );
        }

        $month = DateTimeImmutable::createFromFormat(
            '!Y-m-d',
            $period
        );

        if (
            $month === false ||
            $month->format('Y-m-d') !== $period ||
            $month->format('d') !== '01'
        ) {
            throw new InvalidArgumentException(
                'El periodo debe ser el primer día de un mes válido.'
            );
        }

        $db = ODB::getInstance();

        if (!$db->beginTransaction()) {
            throw new RuntimeException(
                'No se ha podido iniciar la transacción.'
            );
        }

        try {
            // Primero bloquear el espacio para coordinar
            // membresías, edición y generación.
            $stmt = $db->prepare(
                'SELECT id FROM `space`
                 WHERE id = :space_id FOR UPDATE'
            );
            $stmt->execute(['space_id' => $space_id]);

            if ($stmt->fetch(PDO::FETCH_ASSOC) === false) {
                throw new DomainException(
                    'No tienes acceso a este espacio.'
                );
            }

            $stmt = $db->prepare(
                'SELECT id FROM `space_member`
                 WHERE id_space = :space_id
                   AND id_user = :user_id
                   AND left_at IS NULL
                 LIMIT 1'
            );
            $stmt->execute([
                'space_id' => $space_id,
                'user_id' => $user_id
            ]);

            if ($stmt->fetch(PDO::FETCH_ASSOC) === false) {
                throw new DomainException(
                    'No tienes acceso a este espacio.'
                );
            }

            // Después bloquear la plantilla.
            $stmt = $db->prepare(
                'SELECT id, id_space, id_category, type,
                        amount, concept, day_of_month,
                        start_date, end_date, active,
                        created_at
                 FROM `recurring_transaction`
                 WHERE id = :recurring_id
                   AND id_space = :space_id
                 FOR UPDATE'
            );
            $stmt->execute([
                'recurring_id' => $recurring_id,
                'space_id' => $space_id
            ]);

            $recurring = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!is_array($recurring)) {
                throw new InvalidArgumentException(
                    'La plantilla no pertenece al espacio.'
                );
            }

            // Idempotencia: antes de evaluar la plantilla actual,
            // recuperar una ocurrencia histórica existente.
            $existing = $this->findOccurrence(
                $db,
                $recurring_id,
                $period
            );

            if ($existing !== null) {
                if (!$db->commit()) {
                    throw new RuntimeException(
                        'No se ha podido confirmar la consulta.'
                    );
                }

                return [
                    'occurrence' => $existing,
                    'created' => false
                ];
            }

            if ((int) $recurring['active'] !== 1) {
                throw new InvalidArgumentException(
                    'La plantilla está desactivada.'
                );
            }

            // Nunca generar meses anteriores al de creación.
            $creation_month = substr(
                (string) $recurring['created_at'],
                0,
                7
            ) . '-01';

            $start_month = substr(
                (string) $recurring['start_date'],
                0,
                7
            ) . '-01';

            if (
                $period < $creation_month ||
                $period < $start_month
            ) {
                throw new InvalidArgumentException(
                    'El periodo es anterior al inicio permitido.'
                );
            }

            // Día nominal ajustado al último día real del mes.
            $day = min(
                (int) $recurring['day_of_month'],
                (int) $month->format('t')
            );

            $scheduled_date = $month->format('Y-m-') .
                str_pad((string) $day, 2, '0', STR_PAD_LEFT);

            // Una fecha programada anterior al inicio real
            // o posterior al fin de la plantilla se rechaza.
            if (
                $scheduled_date < $recurring['start_date'] ||
                (
                    $recurring['end_date'] !== null &&
                    $scheduled_date > $recurring['end_date']
                )
            ) {
                throw new InvalidArgumentException(
                    'La mensualidad queda fuera de las fechas de la plantilla.'
                );
            }

            $occurrence = new RecurringOccurrence();
            $occurrence->id_space = $space_id;
            $occurrence->id_recurring_transaction = $recurring_id;
            $occurrence->period = $period;
            $occurrence->scheduled_date = $scheduled_date;
            $occurrence->status = 'pending';
            $occurrence->id_category = (int) $recurring['id_category'];
            $occurrence->type = (string) $recurring['type'];
            $occurrence->amount = (string) $recurring['amount'];
            $occurrence->concept = (string) $recurring['concept'];
            $occurrence->id_transaction = null;
            $occurrence->resolved_at = null;

            if (!$occurrence->save() || $occurrence->id === null) {
                throw new RuntimeException(
                    'No se ha podido generar la mensualidad.'
                );
            }

            if (!$db->commit()) {
                throw new RuntimeException(
                    'No se ha podido confirmar la mensualidad.'
                );
            }

            return [
                'occurrence' => $occurrence,
                'created' => true
            ];
        }
        catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            throw $e;
        }
    }

    /**
     * Recupera una mensualidad, si existe, como modelo.
     *
     * @param PDO $db Conexión compartida.
     * @param int $recurring_id Plantilla.
     * @param string $period Periodo mensual.
     * @return RecurringOccurrence|null Ocurrencia encontrada.
     */
    private function findOccurrence(
        PDO $db,
        int $recurring_id,
        string $period
    ): ?RecurringOccurrence {
        $stmt = $db->prepare(
            'SELECT id, id_space, id_recurring_transaction,
                    period, scheduled_date, status,
                    id_category, type, concept, amount,
                    id_transaction, resolved_at
             FROM `recurring_occurrence`
             WHERE id_recurring_transaction = :recurring_id
               AND period = :period'
        );
        $stmt->execute([
            'recurring_id' => $recurring_id,
            'period' => $period
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!is_array($row)) {
            return null;
        }

        $occurrence = new RecurringOccurrence();
        $occurrence->id = (int) $row['id'];
        $occurrence->id_space = (int) $row['id_space'];
        $occurrence->id_recurring_transaction = (int) $row['id_recurring_transaction'];
        $occurrence->period = $row['period'];
        $occurrence->scheduled_date = $row['scheduled_date'];
        $occurrence->status = $row['status'];
        $occurrence->id_category = (int) $row['id_category'];
        $occurrence->type = $row['type'];
        $occurrence->concept = $row['concept'];
        $occurrence->amount = (string) $row['amount'];
        $occurrence->id_transaction = $row['id_transaction'] === null
            ? null
            : (int) $row['id_transaction'];
        $occurrence->resolved_at = $row['resolved_at'];

        return $occurrence;
    }
}

<?php declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Service\Recurring;

use PDO;
use DateTimeImmutable;
use Throwable;
use DomainException;
use InvalidArgumentException;
use LogicException;
use RuntimeException;
use Osumi\OsumiFramework\ORM\ODB;
use Osumi\OsumiFramework\App\Model\RecurringOccurrence;
use Osumi\OsumiFramework\Core\OService;

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

        return is_array($row)
            ? $this->hydrateOccurrence($row)
            : null;
    }

    /**
     * Consulta las ocurrencias del espacio, con filtro de estado.
     *
     * @param int $space_id Espacio contable.
     * @param int $user_id Usuario autenticado.
     * @param string|null $status Estado opcional.
     * @return list<RecurringOccurrence> Ocurrencias.
     */
    public function getBySpace(
        int $space_id,
        int $user_id,
        ?string $status = null
    ): array {
        if ($space_id <= 0 || $user_id <= 0) {
            throw new InvalidArgumentException(
                'Los identificadores no son válidos.'
            );
        }

        if (
            $status !== null &&
            !in_array(
                $status,
                ['pending', 'confirmed', 'skipped'],
                true
            )
        ) {
            throw new InvalidArgumentException(
                'El estado de la ocurrencia no es válido.'
            );
        }

        $db = ODB::getInstance();

        $this->requireActiveMember($db, $space_id, $user_id);

        $sql = 'SELECT id, id_space, id_recurring_transaction,
                       period, scheduled_date, status,
                       id_category, type, concept, amount,
                       id_transaction, resolved_at
                FROM `recurring_occurrence`
                WHERE id_space = :space_id';

        if ($status !== null) {
            $sql .= ' AND status = :status';
        }

        $sql .= ' ORDER BY scheduled_date DESC, id DESC';

        $stmt = $db->prepare($sql);
        $stmt->bindValue(':space_id', $space_id, PDO::PARAM_INT);

        if ($status !== null) {
            $stmt->bindValue(':status', $status, PDO::PARAM_STR);
        }

        $stmt->execute();

        $list = [];

        while (($row = $stmt->fetch(PDO::FETCH_ASSOC)) !== false) {
            $list[] = $this->hydrateOccurrence($row);
        }

        return $list;
    }

    /**
     * Confirma u omite una ocurrencia pendiente.
     *
     * La confirmación crea el movimiento real y resuelve
     * la ocurrencia en una única transacción ACID.
     *
     * @param int $space_id Espacio.
     * @param int $user_id Usuario que resuelve.
     * @param int $occurrence_id Ocurrencia.
     * @param string $action Acción confirm o skip.
     * @return RecurringOccurrence Ocurrencia resuelta.
     */
    public function resolve(
        int $space_id,
        int $user_id,
        int $occurrence_id,
        string $action
    ): RecurringOccurrence {
        if (
            $space_id <= 0 ||
            $user_id <= 0 ||
            $occurrence_id <= 0
        ) {
            throw new InvalidArgumentException(
                'Los identificadores no son válidos.'
            );
        }

        if (!in_array($action, ['confirm', 'skip'], true)) {
            throw new InvalidArgumentException(
                'La acción de resolución no es válida.'
            );
        }

        $db = ODB::getInstance();

        if (!$db->beginTransaction()) {
            throw new RuntimeException(
                'No se ha podido iniciar la transacción.'
            );
        }

        try {
            // Mismo orden de bloqueo que las operaciones
            // económicas: espacio y después registro afectado.
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

            $this->requireActiveMember(
                $db, $space_id, $user_id
            );

            $stmt = $db->prepare(
                'SELECT id, id_space, id_recurring_transaction,
                        period, scheduled_date, status,
                        id_category, type, concept, amount,
                        id_transaction, resolved_at
                 FROM `recurring_occurrence`
                 WHERE id = :occurrence_id
                   AND id_space = :space_id
                 FOR UPDATE'
            );
            $stmt->execute([
                'occurrence_id' => $occurrence_id,
                'space_id' => $space_id
            ]);

            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!is_array($row)) {
                throw new InvalidArgumentException(
                    'La ocurrencia no pertenece al espacio.'
                );
            }

            if ($row['status'] !== 'pending') {
                throw new LogicException(
                    'La ocurrencia ya ha sido resuelta.'
                );
            }

            $transaction_id = null;

            if ($action === 'confirm') {
                // Usar exactamente la instantánea de la
                // ocurrencia, no los valores actuales
                // de la plantilla recurrente.
                $stmt = $db->prepare(
                    'INSERT INTO `financial_transaction`
                     (id_space, id_user, id_category,
                      type, amount, concept, `date`)
                     VALUES
                     (:space_id, :user_id, :category_id,
                      :type, :amount, :concept, :date)'
                );

                $stmt->execute([
                    'space_id' => $space_id,
                    'user_id' => $user_id,
                    'category_id' => (int) $row['id_category'],
                    'type' => $row['type'],
                    'amount' => (string) $row['amount'],
                    'concept' => $row['concept'],
                    'date' => $row['scheduled_date']
                ]);

                $transaction_id = (int) $db->lastInsertId();

                if ($transaction_id <= 0) {
                    throw new RuntimeException(
                        'No se ha podido registrar el movimiento.'
                    );
                }
            }

            $stmt = $db->prepare(
                'UPDATE `recurring_occurrence`
                 SET status = :status,
                     id_transaction = :transaction_id,
                     resolved_at = NOW()
                 WHERE id = :occurrence_id
                   AND id_space = :space_id
                   AND status = :pending'
            );

            $stmt->bindValue(
                ':status',
                $action === 'confirm' ? 'confirmed' : 'skipped',
                PDO::PARAM_STR
            );
            $stmt->bindValue(
                ':transaction_id',
                $transaction_id,
                $transaction_id === null
                    ? PDO::PARAM_NULL
                    : PDO::PARAM_INT
            );
            $stmt->bindValue(
                ':occurrence_id',
                $occurrence_id,
                PDO::PARAM_INT
            );
            $stmt->bindValue(
                ':space_id',
                $space_id,
                PDO::PARAM_INT
            );
            $stmt->bindValue(
                ':pending',
                'pending',
                PDO::PARAM_STR
            );
            $stmt->execute();

            if ($stmt->rowCount() !== 1) {
                throw new LogicException(
                    'La ocurrencia ya ha sido resuelta.'
                );
            }

            // Recuperar el resultado dentro de la misma
            // transacción, antes de confirmar.
            $stmt = $db->prepare(
                'SELECT id, id_space, id_recurring_transaction,
                        period, scheduled_date, status,
                        id_category, type, concept, amount,
                        id_transaction, resolved_at
                 FROM `recurring_occurrence`
                 WHERE id = :occurrence_id
                   AND id_space = :space_id'
            );
            $stmt->execute([
                'occurrence_id' => $occurrence_id,
                'space_id' => $space_id
            ]);

            $updated = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!is_array($updated)) {
                throw new RuntimeException(
                    'No se ha podido recuperar la ocurrencia.'
                );
            }

            $occurrence = $this->hydrateOccurrence($updated);

            if (!$db->commit()) {
                throw new RuntimeException(
                    'No se ha podido confirmar la resolución.'
                );
            }

            return $occurrence;
        }
        catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            throw $e;
        }
    }

    /**
     * Verifica la membresía activa sobre la conexión actual.
     *
     * @param PDO $db Conexión compartida.
     * @param int $space_id Espacio.
     * @param int $user_id Usuario.
     * @return void
     */
    private function requireActiveMember(
        PDO $db,
        int $space_id,
        int $user_id
    ): void {
        $stmt = $db->prepare(
            'SELECT id FROM `space_member`
             WHERE id_space = :space_id
               AND id_user = :user_id
               AND left_at IS NULL LIMIT 1'
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
    }

    /**
     * Convierte una fila SQL en un modelo de ocurrencia.
     *
     * @param array<string, mixed> $row Fila consultada.
     * @return RecurringOccurrence Ocurrencia.
     */
    private function hydrateOccurrence(array $row): RecurringOccurrence {
        $occurrence = new RecurringOccurrence();
        $occurrence->id = (int) $row['id'];
        $occurrence->id_space = (int) $row['id_space'];
        $occurrence->id_recurring_transaction =
            (int) $row['id_recurring_transaction'];
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

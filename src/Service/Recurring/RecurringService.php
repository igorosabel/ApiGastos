<?php declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Service\Recurring;

use DateTimeImmutable;
use DomainException;
use InvalidArgumentException;
use Osumi\OsumiFramework\App\Model\RecurringTransaction;
use Osumi\OsumiFramework\Core\OService;
use Osumi\OsumiFramework\ORM\ODB;
use PDO;
use RuntimeException;
use Throwable;

class RecurringService extends OService {
    /**
     * Crea una plantilla de ingreso o gasto mensual.
     *
     * @param int $space_id Espacio contable.
     * @param int $user_id Usuario autenticado.
     * @param int $category_id Categoría.
     * @param string $type Tipo.
     * @param string $amount Importe decimal.
     * @param string $concept Concepto obligatorio.
     * @param int $day_of_month Día nominal del mes.
     * @param string $start_date Fecha de inicio.
     * @param string|null $end_date Fecha final opcional.
     * @return RecurringTransaction Plantilla creada.
     */
    public function create(
        int $space_id,
        int $user_id,
        int $category_id,
        string $type,
        string $amount,
        string $concept,
        int $day_of_month,
        string $start_date,
        ?string $end_date = null
    ): RecurringTransaction {
        if (
            $space_id <= 0 ||
            $user_id <= 0 ||
            $category_id <= 0
        ) {
            throw new InvalidArgumentException(
                'Los identificadores no son válidos.'
            );
        }

        if (!in_array($type, ['expense', 'income'], true)) {
            throw new InvalidArgumentException(
                'El tipo recurrente no es válido.'
            );
        }

        if (
            preg_match(
                '/^(?:0|[1-9][0-9]{0,9})\.[0-9]{2}$/D',
                $amount
            ) !== 1 ||
            preg_match('/[1-9]/', $amount) !== 1
        ) {
            throw new InvalidArgumentException(
                'El importe debe ser positivo y tener dos decimales.'
            );
        }

        $concept = trim($concept);

        if ($concept === '' || mb_strlen($concept) > 255) {
            throw new InvalidArgumentException(
                'El concepto debe tener entre 1 y 255 caracteres.'
            );
        }

        if ($day_of_month < 1 || $day_of_month > 31) {
            throw new InvalidArgumentException(
                'El día del mes debe estar entre 1 y 31.'
            );
        }

        $this->validateDate($start_date);

        if ($end_date !== null) {
            $this->validateDate($end_date);

            if ($end_date < $start_date) {
                throw new InvalidArgumentException(
                    'La fecha final no puede ser anterior al inicio.'
                );
            }
        }

        $db = ODB::getInstance();

        if (!$db->beginTransaction()) {
            throw new RuntimeException(
                'No se ha podido iniciar la transacción.'
            );
        }

        try {
            // Mismo orden de bloqueo que las bajas de miembros
            // y las operaciones económicas.
            $stmt = $db->prepare(
                'SELECT id FROM `space`
                 WHERE id = :space_id
                 FOR UPDATE'
            );
            $stmt->execute(['space_id' => $space_id]);

            if ($stmt->fetch(PDO::FETCH_ASSOC) === false) {
                throw new DomainException(
                    'No tienes acceso a este espacio.'
                );
            }

            $this->requireActiveMember(
                $db,
                $space_id,
                $user_id
            );

            $stmt = $db->prepare(
                'SELECT default_type FROM `category`
                 WHERE id = :category_id
                   AND id_space = :space_id'
            );
            $stmt->execute([
                'category_id' => $category_id,
                'space_id' => $space_id
            ]);

            if ($stmt->fetchColumn() !== $type) {
                throw new InvalidArgumentException(
                    'La categoría no pertenece al espacio o tiene otro tipo.'
                );
            }

            $recurring = new RecurringTransaction();
            $recurring->id_space = $space_id;
            $recurring->id_category = $category_id;
            $recurring->type = $type;
            $recurring->amount = $amount;
            $recurring->concept = $concept;
            $recurring->day_of_month = $day_of_month;
            $recurring->start_date = $start_date;
            $recurring->end_date = $end_date;
            $recurring->active = true;

            if (!$recurring->save() || $recurring->id === null) {
                throw new RuntimeException(
                    'No se ha podido guardar la plantilla recurrente.'
                );
            }

            if (!$db->commit()) {
                throw new RuntimeException(
                    'No se ha podido confirmar la plantilla.'
                );
            }

            return $recurring;
        }
        catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            throw $e;
        }
    }

    /**
     * Lista las plantillas del espacio, activas e inactivas.
     *
     * @param int $space_id Espacio solicitado.
     * @param int $user_id Usuario autenticado.
     * @return list<RecurringTransaction> Plantillas.
     */
    public function getBySpace(int $space_id, int $user_id): array {
        if ($space_id <= 0 || $user_id <= 0) {
            throw new InvalidArgumentException(
                'Los identificadores no son válidos.'
            );
        }

        $db = ODB::getInstance();

        $this->requireActiveMember($db, $space_id, $user_id);

        return RecurringTransaction::where(
            ['id_space' => $space_id],
            ['order_by' => 'id#ASC']
        );
    }

    /**
     * Comprueba la pertenencia activa utilizando la conexión actual.
     *
     * @param PDO $db Conexión ORM.
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
    }

    /**
     * Valida una fecha de calendario estricta.
     *
     * @param string $date Fecha YYYY-MM-DD.
     * @return void
     */
    private function validateDate(string $date): void {
        $parsed = DateTimeImmutable::createFromFormat(
            '!Y-m-d',
            $date
        );

        if (
            $parsed === false ||
            $parsed->format('Y-m-d') !== $date
        ) {
            throw new InvalidArgumentException(
                'La fecha recurrente no es válida.'
            );
        }
    }

    /**
     * Actualiza una plantilla sin modificar las ocurrencias ya creadas.
     *
     * @param int $space_id Espacio contable.
     * @param int $user_id Usuario autenticado.
     * @param int $recurring_id Plantilla.
     * @param int $category_id Nueva categoría.
     * @param string $type Tipo.
     * @param string $amount Importe decimal.
     * @param string $concept Concepto.
     * @param int $day_of_month Día mensual.
     * @param string $start_date Fecha inicial.
     * @param string|null $end_date Fecha final.
     * @return RecurringTransaction Plantilla actualizada.
     */
    public function update(
        int $space_id,
        int $user_id,
        int $recurring_id,
        int $category_id,
        string $type,
        string $amount,
        string $concept,
        int $day_of_month,
        string $start_date,
        ?string $end_date = null
    ): RecurringTransaction {
        if (
            $space_id <= 0 ||
            $user_id <= 0 ||
            $recurring_id <= 0 ||
            $category_id <= 0
        ) {
            throw new InvalidArgumentException(
                'Los identificadores no son válidos.'
            );
        }

        if (!in_array($type, ['expense', 'income'], true)) {
            throw new InvalidArgumentException(
                'El tipo recurrente no es válido.'
            );
        }

        if (
            preg_match(
                '/^(?:0|[1-9][0-9]{0,9})\.[0-9]{2}$/D',
                $amount
            ) !== 1 ||
            preg_match('/[1-9]/', $amount) !== 1
        ) {
            throw new InvalidArgumentException(
                'El importe debe ser positivo y tener dos decimales.'
            );
        }

        $concept = trim($concept);

        if ($concept === '' || mb_strlen($concept) > 255) {
            throw new InvalidArgumentException(
                'El concepto debe tener entre 1 y 255 caracteres.'
            );
        }

        if ($day_of_month < 1 || $day_of_month > 31) {
            throw new InvalidArgumentException(
                'El día del mes debe estar entre 1 y 31.'
            );
        }

        $this->validateDate($start_date);

        if ($end_date !== null) {
            $this->validateDate($end_date);

            if ($end_date < $start_date) {
                throw new InvalidArgumentException(
                    'La fecha final no puede ser anterior al inicio.'
                );
            }
        }

        $db = ODB::getInstance();

        if (!$db->beginTransaction()) {
            throw new RuntimeException(
                'No se ha podido iniciar la transacción.'
            );
        }

        try {
            $this->lockSpaceAndRequireMember(
                $db, $space_id, $user_id
            );

            $stmt = $db->prepare(
                'SELECT id FROM `recurring_transaction`
                 WHERE id = :recurring_id
                   AND id_space = :space_id
                 FOR UPDATE'
            );
            $stmt->execute([
                'recurring_id' => $recurring_id,
                'space_id' => $space_id
            ]);

            if ($stmt->fetch(PDO::FETCH_ASSOC) === false) {
                throw new InvalidArgumentException(
                    'La plantilla no pertenece al espacio.'
                );
            }

            $stmt = $db->prepare(
                'SELECT default_type FROM `category`
                 WHERE id = :category_id
                   AND id_space = :space_id'
            );
            $stmt->execute([
                'category_id' => $category_id,
                'space_id' => $space_id
            ]);

            if ($stmt->fetchColumn() !== $type) {
                throw new InvalidArgumentException(
                    'La categoría no pertenece al espacio o tiene otro tipo.'
                );
            }

            $stmt = $db->prepare(
                'UPDATE `recurring_transaction`
                 SET id_category = :category_id,
                     type = :type,
                     amount = :amount,
                     concept = :concept,
                     day_of_month = :day_of_month,
                     start_date = :start_date,
                     end_date = :end_date
                 WHERE id = :recurring_id
                   AND id_space = :space_id'
            );

            $stmt->bindValue(
                ':category_id', $category_id, PDO::PARAM_INT
            );
            $stmt->bindValue(':type', $type, PDO::PARAM_STR);
            $stmt->bindValue(':amount', $amount, PDO::PARAM_STR);
            $stmt->bindValue(':concept', $concept, PDO::PARAM_STR);
            $stmt->bindValue(
                ':day_of_month', $day_of_month, PDO::PARAM_INT
            );
            $stmt->bindValue(
                ':start_date', $start_date, PDO::PARAM_STR
            );
            $stmt->bindValue(
                ':end_date',
                $end_date,
                $end_date === null
                    ? PDO::PARAM_NULL
                    : PDO::PARAM_STR
            );
            $stmt->bindValue(
                ':recurring_id', $recurring_id, PDO::PARAM_INT
            );
            $stmt->bindValue(
                ':space_id', $space_id, PDO::PARAM_INT
            );

            $stmt->execute();

            if (!$db->commit()) {
                throw new RuntimeException(
                    'No se ha podido confirmar la actualización.'
                );
            }

            return $this->loadRecurring($db, $space_id, $recurring_id);
        }
        catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            throw $e;
        }
    }

    /**
     * Activa o desactiva una plantilla sin eliminar su historial.
     *
     * @param int $space_id Espacio.
     * @param int $user_id Usuario autenticado.
     * @param int $recurring_id Plantilla.
     * @param bool $active Nuevo estado.
     * @return RecurringTransaction Plantilla actualizada.
     */
    public function setActive(
        int $space_id,
        int $user_id,
        int $recurring_id,
        bool $active
    ): RecurringTransaction {
        if (
            $space_id <= 0 ||
            $user_id <= 0 ||
            $recurring_id <= 0
        ) {
            throw new InvalidArgumentException(
                'Los identificadores no son válidos.'
            );
        }

        $db = ODB::getInstance();

        if (!$db->beginTransaction()) {
            throw new RuntimeException(
                'No se ha podido iniciar la transacción.'
            );
        }

        try {
            $this->lockSpaceAndRequireMember(
                $db, $space_id, $user_id
            );

            $stmt = $db->prepare(
                'SELECT id FROM `recurring_transaction`
                 WHERE id = :recurring_id
                   AND id_space = :space_id
                 FOR UPDATE'
            );
            $stmt->execute([
                'recurring_id' => $recurring_id,
                'space_id' => $space_id
            ]);

            if ($stmt->fetch(PDO::FETCH_ASSOC) === false) {
                throw new InvalidArgumentException(
                    'La plantilla no pertenece al espacio.'
                );
            }

            $stmt = $db->prepare(
                'UPDATE `recurring_transaction`
                 SET active = :active
                 WHERE id = :recurring_id
                   AND id_space = :space_id'
            );
            $stmt->bindValue(
                ':active', $active ? 1 : 0, PDO::PARAM_INT
            );
            $stmt->bindValue(
                ':recurring_id', $recurring_id, PDO::PARAM_INT
            );
            $stmt->bindValue(
                ':space_id', $space_id, PDO::PARAM_INT
            );
            $stmt->execute();

            if (!$db->commit()) {
                throw new RuntimeException(
                    'No se ha podido confirmar el estado.'
                );
            }

            return $this->loadRecurring($db, $space_id, $recurring_id);
        }
        catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            throw $e;
        }
    }

    /**
     * Bloquea el espacio y comprueba la membresía actual.
     *
     * @param PDO $db Conexión.
     * @param int $space_id Espacio.
     * @param int $user_id Usuario.
     * @return void
     */
    private function lockSpaceAndRequireMember(
        PDO $db,
        int $space_id,
        int $user_id
    ): void {
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

        $this->requireActiveMember($db, $space_id, $user_id);
    }

    /**
     * Recupera una plantilla con los datos almacenados.
     *
     * @param PDO $db Conexión.
     * @param int $space_id Espacio.
     * @param int $recurring_id Plantilla.
     * @return RecurringTransaction Plantilla.
     */
    private function loadRecurring(
        PDO $db,
        int $space_id,
        int $recurring_id
    ): RecurringTransaction {
        $stmt = $db->prepare(
            'SELECT id, id_space, id_category, type, amount,
                    concept, day_of_month, start_date,
                    end_date, active
             FROM `recurring_transaction`
             WHERE id = :recurring_id
               AND id_space = :space_id'
        );
        $stmt->execute([
            'recurring_id' => $recurring_id,
            'space_id' => $space_id
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!is_array($row)) {
            throw new RuntimeException(
                'No se ha podido recuperar la plantilla.'
            );
        }

        $recurring = new RecurringTransaction();
        $recurring->id = (int) $row['id'];
        $recurring->id_space = (int) $row['id_space'];
        $recurring->id_category = (int) $row['id_category'];
        $recurring->type = $row['type'];
        $recurring->amount = (string) $row['amount'];
        $recurring->concept = $row['concept'];
        $recurring->day_of_month = (int) $row['day_of_month'];
        $recurring->start_date = $row['start_date'];
        $recurring->end_date = $row['end_date'];
        $recurring->active = (bool) $row['active'];

        return $recurring;
    }
}

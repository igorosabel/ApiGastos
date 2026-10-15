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
}

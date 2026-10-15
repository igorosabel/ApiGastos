<?php declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Service\Transaction;

use DateTimeImmutable;
use DomainException;
use InvalidArgumentException;
use Osumi\OsumiFramework\App\Model\FinancialTransaction;
use Osumi\OsumiFramework\Core\OService;
use Osumi\OsumiFramework\ORM\ODB;
use PDO;
use RuntimeException;
use Throwable;

class TransactionService extends OService {
    /**
     * Registra un ingreso o gasto de un miembro activo.
     *
     * @param int $space_id Espacio contable.
     * @param int $user_id Usuario autenticado.
     * @param int $category_id Categoría.
     * @param string $type Tipo de movimiento.
     * @param string $amount Importe decimal.
     * @param string|null $concept Concepto.
     * @param string $date Fecha contable.
     * @return FinancialTransaction Movimiento creado.
     */
    public function create(
        int $space_id,
        int $user_id,
        int $category_id,
        string $type,
        string $amount,
        ?string $concept,
        string $date
    ): FinancialTransaction {
        if ($space_id <= 0 || $user_id <= 0 || $category_id <= 0) {
            throw new InvalidArgumentException(
                'Los identificadores no son válidos.'
            );
        }

        if (!in_array($type, ['expense', 'income'], true)) {
            throw new InvalidArgumentException(
                'El tipo de movimiento no es válido.'
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

        $concept = $concept === null ? null : trim($concept);

        if ($concept !== null) {
            if (mb_strlen($concept) > 255) {
                throw new InvalidArgumentException(
                    'El concepto no puede superar los 255 caracteres.'
                );
            }

            if ($concept === '') {
                $concept = null;
            }
        }

        $parsed_date = DateTimeImmutable::createFromFormat(
            '!Y-m-d',
            $date
        );

        if (
            $parsed_date === false ||
            $parsed_date->format('Y-m-d') !== $date
        ) {
            throw new InvalidArgumentException(
                'La fecha del movimiento no es válida.'
            );
        }

        $db = ODB::getInstance();

        if (!$db->beginTransaction()) {
            throw new RuntimeException(
                'No se ha podido iniciar la transacción.'
            );
        }

        try {
            // El bloqueo coordina la creación de movimientos
            // con las operaciones de baja de miembros.
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

            $stmt = $db->prepare(
                'SELECT default_type FROM `category`
                 WHERE id = :category_id
                   AND id_space = :space_id'
            );
            $stmt->execute([
                'category_id' => $category_id,
                'space_id' => $space_id
            ]);

            $category_type = $stmt->fetchColumn();

            if ($category_type === false || $category_type !== $type) {
                throw new InvalidArgumentException(
                    'La categoría no pertenece al espacio o tiene otro tipo.'
                );
            }

            $transaction = new FinancialTransaction();
            $transaction->id_space = $space_id;
            $transaction->id_user = $user_id;
            $transaction->id_category = $category_id;
            $transaction->type = $type;
            $transaction->amount = $amount;
            $transaction->concept = $concept;
            $transaction->date = $date;

            if (!$transaction->save() || $transaction->id === null) {
                throw new RuntimeException(
                    'No se ha podido guardar el movimiento.'
                );
            }

            if (!$db->commit()) {
                throw new RuntimeException(
                    'No se ha podido confirmar el movimiento.'
                );
            }

            return $transaction;
        }
        catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            throw $e;
        }
    }

    /**
     * Obtiene los movimientos de un espacio autorizado.
     *
     * @param int $space_id Espacio contable.
     * @param int $user_id Usuario autenticado.
     * @return list<FinancialTransaction> Movimientos.
     */
    public function getBySpace(int $space_id, int $user_id): array {
        if ($space_id <= 0 || $user_id <= 0) {
            throw new InvalidArgumentException(
                'Los identificadores no son válidos.'
            );
        }

        $db = ODB::getInstance();

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

        $transactions = FinancialTransaction::where([
            'id_space' => $space_id
        ]);

        usort(
            $transactions,
            static fn(
                FinancialTransaction $a,
                FinancialTransaction $b
            ): int => [
                $b->date,
                $b->id
            ] <=> [
                $a->date,
                $a->id
            ]
        );

        return $transactions;
    }
}

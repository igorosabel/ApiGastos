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

    /**
     * Busca movimientos filtrados y paginados de un espacio.
     *
     * @param int $space_id Espacio contable.
     * @param int $user_id Usuario autenticado.
     * @param string|null $date_from Fecha inicial incluida.
     * @param string|null $date_to Fecha final incluida.
     * @param string|null $type Tipo de movimiento.
     * @param int|null $category_id Categoría concreta.
     * @param int $page Número de página.
     * @param int $page_size Registros por página.
     * @return array{
     *     transactions: list<FinancialTransaction>,
     *     total: int,
     *     page: int,
     *     page_size: int,
     *     total_pages: int
     * }
     */
    public function search(
        int $space_id,
        int $user_id,
        ?string $date_from = null,
        ?string $date_to = null,
        ?string $type = null,
        ?int $category_id = null,
        int $page = 1,
        int $page_size = 20
    ): array {
        if ($space_id <= 0 || $user_id <= 0) {
            throw new InvalidArgumentException(
                'Los identificadores no son válidos.'
            );
        }

        if (
            $page < 1 ||
            $page > 100000 ||
            $page_size < 1 ||
            $page_size > 100
        ) {
            throw new InvalidArgumentException(
                'Los parámetros de paginación no son válidos.'
            );
        }

        if (
            $type !== null &&
            !in_array($type, ['expense', 'income'], true)
        ) {
            throw new InvalidArgumentException(
                'El tipo de movimiento no es válido.'
            );
        }

        if ($category_id !== null && $category_id <= 0) {
            throw new InvalidArgumentException(
                'La categoría no es válida.'
            );
        }

        foreach ([$date_from, $date_to] as $date) {
            if ($date === null) {
                continue;
            }

            $parsed = DateTimeImmutable::createFromFormat(
                '!Y-m-d',
                $date
            );

            if (
                $parsed === false ||
                $parsed->format('Y-m-d') !== $date
            ) {
                throw new InvalidArgumentException(
                    'El formato de las fechas no es válido.'
                );
            }
        }

        if (
            $date_from !== null &&
            $date_to !== null &&
            $date_from > $date_to
        ) {
            throw new InvalidArgumentException(
                'La fecha inicial no puede superar la final.'
            );
        }

        $db = ODB::getInstance();

        // Comprobamos la pertenencia activa antes de consultar datos.
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

        $where = ['id_space = :space_id'];
        $params = [
            'space_id' => [$space_id, PDO::PARAM_INT]
        ];

        if ($date_from !== null) {
            $where[] = '`date` >= :date_from';
            $params['date_from'] = [$date_from, PDO::PARAM_STR];
        }

        if ($date_to !== null) {
            $where[] = '`date` <= :date_to';
            $params['date_to'] = [$date_to, PDO::PARAM_STR];
        }

        if ($type !== null) {
            $where[] = '`type` = :type';
            $params['type'] = [$type, PDO::PARAM_STR];
        }

        if ($category_id !== null) {
            $where[] = 'id_category = :category_id';
            $params['category_id'] = [
                $category_id,
                PDO::PARAM_INT
            ];
        }

        $where_sql = implode(' AND ', $where);

        $bind_filters = static function(
            \PDOStatement $stmt,
            array $params
        ): void {
            foreach ($params as $name => [$value, $pdo_type]) {
                $stmt->bindValue(
                    ':' . $name,
                    $value,
                    $pdo_type
                );
            }
        };

        // El total usa exactamente los mismos filtros.
        $stmt = $db->prepare(
            'SELECT COUNT(*)
             FROM `financial_transaction`
             WHERE ' . $where_sql
        );
        $bind_filters($stmt, $params);
        $stmt->execute();

        $total = (int) $stmt->fetchColumn();

        // El ID desempata movimientos de la misma fecha.
        $offset = ($page - 1) * $page_size;

        $stmt = $db->prepare(
            'SELECT id, id_space, id_user, id_category,
                    type, amount, concept, date
             FROM `financial_transaction`
             WHERE ' . $where_sql . '
             ORDER BY `date` DESC, id DESC
             LIMIT :page_size OFFSET :offset'
        );

        $bind_filters($stmt, $params);
        $stmt->bindValue(
            ':page_size',
            $page_size,
            PDO::PARAM_INT
        );
        $stmt->bindValue(
            ':offset',
            $offset,
            PDO::PARAM_INT
        );
        $stmt->execute();

        $transactions = [];

        while (($row = $stmt->fetch(PDO::FETCH_ASSOC)) !== false) {
            $transaction = new FinancialTransaction();
            $transaction->id = (int) $row['id'];
            $transaction->id_space = (int) $row['id_space'];
            $transaction->id_user = (int) $row['id_user'];
            $transaction->id_category = (int) $row['id_category'];
            $transaction->type = $row['type'];
            $transaction->amount = (string) $row['amount'];
            $transaction->concept = $row['concept'];
            $transaction->date = $row['date'];

            $transactions[] = $transaction;
        }

        return [
            'transactions' => $transactions,
            'total' => $total,
            'page' => $page,
            'page_size' => $page_size,
            'total_pages' => (int) ceil($total / $page_size)
        ];
    }
}

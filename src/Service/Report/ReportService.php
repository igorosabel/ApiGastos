<?php declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Service\Report;

use DateTimeImmutable;
use DomainException;
use InvalidArgumentException;
use Osumi\OsumiFramework\Core\OService;
use Osumi\OsumiFramework\ORM\ODB;
use PDO;

class ReportService extends OService {
    /**
     * Obtiene el resumen económico real de un mes.
     *
     * No incluye las ocurrencias recurrentes que
     * todavía no se han convertido en movimientos.
     *
     * @param int $space_id Espacio contable.
     * @param int $user_id Usuario autenticado.
     * @param string $month Mes YYYY-MM.
     * @return array{
     *     space_id: int,
     *     month: string,
     *     income: string,
     *     expense: string,
     *     balance: string,
     *     income_count: int,
     *     expense_count: int,
     *     transaction_count: int
     * }
     */
    public function getMonthly(
        int $space_id,
        int $user_id,
        string $month
    ): array {
        if ($space_id <= 0 || $user_id <= 0) {
            throw new InvalidArgumentException(
                'Los identificadores no son válidos.'
            );
        }

        if (
            preg_match('/^[0-9]{4}-(0[1-9]|1[0-2])$/D', $month) !== 1
        ) {
            throw new InvalidArgumentException(
                'El mes debe tener formato YYYY-MM.'
            );
        }

        $start = DateTimeImmutable::createFromFormat(
            '!Y-m-d',
            $month . '-01'
        );

        if (
            $start === false ||
            $start->format('Y-m') !== $month
        ) {
            throw new InvalidArgumentException(
                'El mes no es válido.'
            );
        }

        $date_from = $start->format('Y-m-d');
        $date_to = $start->modify('+1 month')->format('Y-m-d');

        $db = ODB::getInstance();

        // Comprobamos la membresía activa antes de
        // acceder a datos económicos del espacio.
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

        // Una sola agregación, exclusivamente sobre
        // movimientos económicos persistidos.
        $stmt = $db->prepare(
            'SELECT
                COALESCE(
                    SUM(
                        CASE WHEN `type` = \'income\'
                        THEN amount ELSE 0 END
                    ),
                    0
                ) AS income,
                COALESCE(
                    SUM(
                        CASE WHEN `type` = \'expense\'
                        THEN amount ELSE 0 END
                    ),
                    0
                ) AS expense,
                COALESCE(
                    SUM(
                        CASE
                            WHEN `type` = \'income\' THEN amount
                            WHEN `type` = \'expense\' THEN -amount
                            ELSE 0
                        END
                    ),
                    0
                ) AS balance,
                COUNT(
                    CASE WHEN `type` = \'income\' THEN 1 END
                ) AS income_count,
                COUNT(
                    CASE WHEN `type` = \'expense\' THEN 1 END
                ) AS expense_count,
                COUNT(*) AS transaction_count
             FROM `financial_transaction`
             WHERE id_space = :space_id
               AND `date` >= :date_from
               AND `date` < :date_to'
        );

        $stmt->execute([
            'space_id' => $space_id,
            'date_from' => $date_from,
            'date_to' => $date_to
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return [
            'space_id' => $space_id,
            'month' => $month,
            'income' => $this->formatAmount($row['income']),
            'expense' => $this->formatAmount($row['expense']),
            'balance' => $this->formatAmount($row['balance']),
            'income_count' => (int) $row['income_count'],
            'expense_count' => (int) $row['expense_count'],
            'transaction_count' => (int) $row['transaction_count']
        ];
    }

    /**
     * Garantiza un importe textual con dos decimales
     * sin realizar conversiones a coma flotante.
     *
     * @param string|int|null $amount Resultado SQL.
     * @return string Importe normalizado.
     */
    private function formatAmount(string|int|null $amount): string {
        $value = (string) ($amount ?? '0');

        if (preg_match('/^-?[0-9]+(?:\.[0-9]+)?$/D', $value) !== 1) {
            throw new \RuntimeException(
                'La base de datos ha devuelto un importe inválido.'
            );
        }

        $negative = str_starts_with($value, '-');

        if ($negative) {
            $value = substr($value, 1);
        }

        $parts = explode('.', $value, 2);
        $integer = ltrim($parts[0], '0');
        $integer = $integer === '' ? '0' : $integer;
        $decimal = str_pad($parts[1] ?? '', 2, '0');

        // Los valores de SUM(DECIMAL(...,2)) deben
        // mantener como máximo dos decimales.
        if (strlen($decimal) > 2) {
            throw new \RuntimeException(
                'La base de datos ha devuelto demasiados decimales.'
            );
        }

        $normalized = $integer . '.' . $decimal;

        return $negative && $normalized !== '0.00'
            ? '-' . $normalized
            : $normalized;
    }


    /**
     * Obtiene el desglose económico mensual por categoría directa.
     *
     * No agrega descendientes en categorías padre.
     * Solo considera movimientos económicos reales.
     *
     * @param int $space_id Espacio contable.
     * @param int $user_id Usuario autenticado.
     * @param string $month Mes YYYY-MM.
     * @return array{
     *     space_id: int,
     *     month: string,
     *     categories: list<array{
     *         category_id: int,
     *         parent_id: ?int,
     *         name: string,
     *         type: string,
     *         amount: string,
     *         transaction_count: int
     *     }>
     * }
     */
    public function getMonthlyByCategory(
        int $space_id,
        int $user_id,
        string $month
    ): array {
        // Reutiliza la validación de mes y autorización
        // del informe mensual ya implementado.
        $this->getMonthly($space_id, $user_id, $month);

        $start = DateTimeImmutable::createFromFormat(
            '!Y-m-d',
            $month . '-01'
        );

        $date_from = $start->format('Y-m-d');
        $date_to = $start->modify('+1 month')->format('Y-m-d');

        $db = ODB::getInstance();

        $stmt = $db->prepare(
            'SELECT
                c.id AS category_id,
                c.id_parent AS parent_id,
                c.name,
                ft.type,
                SUM(ft.amount) AS amount,
                COUNT(*) AS transaction_count
             FROM `financial_transaction` ft
             INNER JOIN `category` c
               ON c.id_space = ft.id_space
              AND c.id = ft.id_category
             WHERE ft.id_space = :space_id
               AND ft.`date` >= :date_from
               AND ft.`date` < :date_to
             GROUP BY
                c.id, c.id_parent, c.name, ft.type
             ORDER BY
                ft.type ASC,
                amount DESC,
                c.id ASC'
        );

        $stmt->execute([
            'space_id' => $space_id,
            'date_from' => $date_from,
            'date_to' => $date_to
        ]);

        $categories = [];

        while (($row = $stmt->fetch(PDO::FETCH_ASSOC)) !== false) {
            $categories[] = [
                'category_id' => (int) $row['category_id'],
                'parent_id' => $row['parent_id'] === null
                    ? null
                    : (int) $row['parent_id'],
                'name' => (string) $row['name'],
                'type' => (string) $row['type'],
                'amount' => $this->formatAmount($row['amount']),
                'transaction_count' => (int) $row['transaction_count']
            ];
        }

        return [
            'space_id' => $space_id,
            'month' => $month,
            'categories' => $categories
        ];
    }

    /**
     * Devuelve una serie mensual de resultados económicos reales.
     *
     * Ambos meses se incluyen y se permite un máximo de 24.
     * Los meses sin movimientos figuran con importes cero.
     *
     * @param int $space_id Espacio contable.
     * @param int $user_id Usuario autenticado.
     * @param string $month_from Primer mes YYYY-MM.
     * @param string $month_to Último mes YYYY-MM.
     * @return array{
     *     space_id: int,
     *     month_from: string,
     *     month_to: string,
     *     months: list<array<string, int|string>>
     * }
     */
    public function getEvolution(
        int $space_id,
        int $user_id,
        string $month_from,
        string $month_to
    ): array {
        if ($space_id <= 0 || $user_id <= 0) {
            throw new InvalidArgumentException(
                'Los identificadores no son válidos.'
            );
        }

        [$from, $to] = $this->validateEvolutionRange(
            $month_from,
            $month_to
        );

        $months = [];

        for (
            $current = $from;
            $current <= $to;
            $current = $current->modify('+1 month')
        ) {
            // getMonthly() verifica permisos e importa
            // directamente del libro real de movimientos.
            $months[] = $this->getMonthly(
                $space_id,
                $user_id,
                $current->format('Y-m')
            );
        }

        return [
            'space_id' => $space_id,
            'month_from' => $month_from,
            'month_to' => $month_to,
            'months' => $months
        ];
    }

    /**
     * Obtiene el resultado real, pendiente y proyectado
     * de un mes para un espacio contable.
     *
     * Solo se incluyen ocurrencias ya generadas con
     * status pending. Las confirmadas están representadas
     * exclusivamente por su movimiento económico real.
     *
     * @param int $space_id Espacio contable.
     * @param int $user_id Usuario autenticado.
     * @param string $month Mes YYYY-MM.
     * @return array{
     *     space_id: int,
     *     month: string,
     *     actual: array<string, int|string>,
     *     pending_income: string,
     *     pending_expense: string,
     *     pending_balance: string,
     *     pending_income_count: int,
     *     pending_expense_count: int,
     *     pending_occurrence_count: int,
     *     projected_income: string,
     *     projected_expense: string,
     *     projected_balance: string
     * }
     */
    public function getMonthlyForecast(
        int $space_id,
        int $user_id,
        string $month
    ): array {
        // Valida el mes, comprueba la membresía activa
        // y obtiene el resultado económico real.
        $actual = $this->getMonthly(
            $space_id,
            $user_id,
            $month
        );

        $start = DateTimeImmutable::createFromFormat(
            '!Y-m-d',
            $month . '-01'
        );

        $date_from = $start->format('Y-m-d');
        $date_to = $start->modify('+1 month')->format('Y-m-d');

        $db = ODB::getInstance();

        // La subconsulta agrega exclusivamente pendientes.
        // La consulta exterior suma los importes reales
        // mediante operaciones DECIMAL de MariaDB.
        $stmt = $db->prepare(
            'SELECT
                p.pending_income,
                p.pending_expense,
                p.pending_balance,
                p.pending_income_count,
                p.pending_expense_count,
                p.pending_occurrence_count,
                CAST(:actual_income AS DECIMAL(30,2))
                    + p.pending_income AS projected_income,
                CAST(:actual_expense AS DECIMAL(30,2))
                    + p.pending_expense AS projected_expense,
                CAST(:actual_balance AS DECIMAL(30,2))
                    + p.pending_balance AS projected_balance
             FROM (
                SELECT
                    COALESCE(
                        SUM(
                            CASE WHEN `type` = \'income\'
                            THEN amount ELSE 0 END
                        ),
                        0
                    ) AS pending_income,
                    COALESCE(
                        SUM(
                            CASE WHEN `type` = \'expense\'
                            THEN amount ELSE 0 END
                        ),
                        0
                    ) AS pending_expense,
                    COALESCE(
                        SUM(
                            CASE
                                WHEN `type` = \'income\' THEN amount
                                WHEN `type` = \'expense\' THEN -amount
                                ELSE 0
                            END
                        ),
                        0
                    ) AS pending_balance,
                    COUNT(
                        CASE WHEN `type` = \'income\'
                        THEN 1 END
                    ) AS pending_income_count,
                    COUNT(
                        CASE WHEN `type` = \'expense\'
                        THEN 1 END
                    ) AS pending_expense_count,
                    COUNT(*) AS pending_occurrence_count
                FROM `recurring_occurrence`
                WHERE id_space = :space_id
                  AND status = \'pending\'
                  AND scheduled_date >= :date_from
                  AND scheduled_date < :date_to
             ) p'
        );

        $stmt->execute([
            'actual_income' => $actual['income'],
            'actual_expense' => $actual['expense'],
            'actual_balance' => $actual['balance'],
            'space_id' => $space_id,
            'date_from' => $date_from,
            'date_to' => $date_to
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!is_array($row)) {
            throw new \RuntimeException(
                'No se ha podido calcular la previsión.'
            );
        }

        return [
            'space_id' => $space_id,
            'month' => $month,
            'actual' => $actual,
            'pending_income' => $this->formatAmount(
                $row['pending_income']
            ),
            'pending_expense' => $this->formatAmount(
                $row['pending_expense']
            ),
            'pending_balance' => $this->formatAmount(
                $row['pending_balance']
            ),
            'pending_income_count' => (int) $row['pending_income_count'],
            'pending_expense_count' => (int) $row['pending_expense_count'],
            'pending_occurrence_count' => (int) $row['pending_occurrence_count'],
            'projected_income' => $this->formatAmount(
                $row['projected_income']
            ),
            'projected_expense' => $this->formatAmount(
                $row['projected_expense']
            ),
            'projected_balance' => $this->formatAmount(
                $row['projected_balance']
            )
        ];
    }

    /**
     * Obtiene la evolución de resultados reales,
     * pendientes y proyectados de un espacio.
     *
     * No genera ocurrencias ni movimientos. Cada mes
     * conserva el contrato de getMonthlyForecast().
     *
     * @param int $space_id Espacio contable.
     * @param int $user_id Usuario autenticado.
     * @param string $month_from Primer mes incluido.
     * @param string $month_to Último mes incluido.
     * @return array{
     *     space_id: int,
     *     month_from: string,
     *     month_to: string,
     *     months: list<array<string, mixed>>
     * }
     */
    public function getForecastEvolution(
        int $space_id,
        int $user_id,
        string $month_from,
        string $month_to
    ): array {
        if ($space_id <= 0 || $user_id <= 0) {
            throw new InvalidArgumentException(
                'Los identificadores no son válidos.'
            );
        }

        [$from, $to] = $this->validateEvolutionRange(
            $month_from,
            $month_to
        );

        $months = [];

        for (
            $current = $from;
            $current <= $to;
            $current = $current->modify('+1 month')
        ) {
            $months[] = $this->getMonthlyForecast(
                $space_id,
                $user_id,
                $current->format('Y-m')
            );
        }

        return [
            'space_id' => $space_id,
            'month_from' => $month_from,
            'month_to' => $month_to,
            'months' => $months
        ];
    }

    /**
     * Valida los límites de una serie mensual.
     *
     * Permite un máximo de 24 meses, ambos incluidos.
     *
     * @param string $month_from Primer mes YYYY-MM.
     * @param string $month_to Último mes YYYY-MM.
     * @return array{DateTimeImmutable, DateTimeImmutable}
     */
    private function validateEvolutionRange(
        string $month_from,
        string $month_to
    ): array {
        $parse_month = static function(
            string $value
        ): DateTimeImmutable {
            if (
                preg_match(
                    '/^[0-9]{4}-(0[1-9]|1[0-2])$/D',
                    $value
                ) !== 1
            ) {
                throw new InvalidArgumentException(
                    'El mes debe tener formato YYYY-MM.'
                );
            }

            $parsed = DateTimeImmutable::createFromFormat(
                '!Y-m-d',
                $value . '-01'
            );

            if (
                $parsed === false ||
                $parsed->format('Y-m') !== $value
            ) {
                throw new InvalidArgumentException(
                    'El mes no es válido.'
                );
            }

            return $parsed;
        };

        $from = $parse_month($month_from);
        $to = $parse_month($month_to);

        if ($from > $to) {
            throw new InvalidArgumentException(
                'El mes inicial no puede superar el final.'
            );
        }

        if ($to >= $from->modify('+24 months')) {
            throw new InvalidArgumentException(
                'El intervalo no puede superar 24 meses.'
            );
        }

        return [$from, $to];
    }
}

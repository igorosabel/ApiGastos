<?php declare(strict_types=1);

require_once dirname(__DIR__) . '/OrmIntegrationTestCase.php';

use Osumi\OsumiFramework\App\Service\Category\CategoryService;
use Osumi\OsumiFramework\App\Service\Report\ReportService;
use Osumi\OsumiFramework\App\Service\Space\SpaceService;
use Osumi\OsumiFramework\App\Service\Transaction\TransactionService;

final class ReportServiceIntegrationTest extends OrmIntegrationTestCase {
    /** @var list<int> */
    private array $users = [];

    /** @var list<int> */
    private array $spaces = [];

    /**
     * Limpia los registros de integración.
     *
     * Respeta las claves foráneas y elimina las categorías
     * desde las hojas hacia las raíces.
     */
    protected function tearDown(): void {
        try {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            foreach (array_reverse($this->spaces) as $space_id) {
                // Primero eliminar los registros que dependen
                // de las categorías y de los movimientos.
                foreach ([
                    'recurring_occurrence',
                    'recurring_transaction',
                    'financial_transaction'
                ] as $table) {
                    $stmt = $this->db->prepare(
                        "DELETE FROM `{$table}` WHERE id_space = ?"
                    );
                    $stmt->execute([$space_id]);
                }

                // Eliminar las categorías desde las hojas
                // hacia las raíces para respetar la FK
                // autorreferenciada.
                $delete = $this->db->prepare(
                    'DELETE c FROM `category` c
                     LEFT JOIN `category` child
                       ON child.id_space = c.id_space
                      AND child.id_parent = c.id
                     WHERE c.id_space = ?
                       AND child.id IS NULL'
                );

                do {
                    $delete->execute([$space_id]);
                    $deleted = $delete->rowCount();
                } while ($deleted > 0);

                // Comprobar que todas las categorías
                // del espacio se han eliminado.
                $stmt = $this->db->prepare(
                    'SELECT COUNT(*) FROM `category`
                     WHERE id_space = ?'
                );
                $stmt->execute([$space_id]);

                $this->assertSame(
                    0,
                    (int) $stmt->fetchColumn(),
                    'Quedan categorías sin eliminar.'
                );

                // Eliminar las membresías.
                $stmt = $this->db->prepare(
                    'DELETE FROM `space_member`
                     WHERE id_space = ?'
                );
                $stmt->execute([$space_id]);

                // Eliminar el espacio.
                $stmt = $this->db->prepare(
                    'DELETE FROM `space` WHERE id = ?'
                );
                $stmt->execute([$space_id]);
            }

            // Eliminar los usuarios creados por la prueba.
            foreach (array_reverse($this->users) as $user_id) {
                $stmt = $this->db->prepare(
                    'DELETE FROM `user` WHERE id = ?'
                );
                $stmt->execute([$user_id]);
            }
        }
        finally {
            parent::tearDown();
        }
    }

    /**
     * Comprueba ingresos, gastos, balance y contadores.
     */
    public function testMonthlyTotals(): void {
        [$space, $user, $expense, $income] = $this->prepareSpace();

        $this->transaction($space, $user, $income, 'income', '2500.00', '2026-10-01');
        $this->transaction($space, $user, $expense, 'expense', '842.75', '2026-10-15');
        $this->transaction($space, $user, $expense, 'expense', '1000.00', '2026-10-31');
        $this->transaction($space, $user, $income, 'income', '75.00', '2026-11-01');

        $result = (new ReportService())->getMonthly($space, $user, '2026-10');

        $this->assertSame($space, $result['space_id']);
        $this->assertSame('2026-10', $result['month']);
        $this->assertSame('2500.00', $result['income']);
        $this->assertSame('1842.75', $result['expense']);
        $this->assertSame('657.25', $result['balance']);
        $this->assertSame(1, $result['income_count']);
        $this->assertSame(2, $result['expense_count']);
        $this->assertSame(3, $result['transaction_count']);
    }

    /**
     * Comprueba un mes sin movimientos.
     */
    public function testEmptyMonth(): void {
        [$space, $user] = $this->prepareSpace();

        $result = (new ReportService())->getMonthly($space, $user, '2026-10');

        $this->assertSame('0.00', $result['income']);
        $this->assertSame('0.00', $result['expense']);
        $this->assertSame('0.00', $result['balance']);
        $this->assertSame(0, $result['income_count']);
        $this->assertSame(0, $result['expense_count']);
        $this->assertSame(0, $result['transaction_count']);
    }

    /**
     * Comprueba saldo negativo y decimales exactos.
     */
    public function testNegativeBalanceAndPrecision(): void {
        [$space, $user, $expense, $income] = $this->prepareSpace();

        $this->transaction($space, $user, $income, 'income', '0.10', '2026-10-10');
        $this->transaction($space, $user, $income, 'income', '0.20', '2026-10-10');
        $this->transaction($space, $user, $expense, 'expense', '0.40', '2026-10-11');

        $result = (new ReportService())->getMonthly($space, $user, '2026-10');

        $this->assertSame('0.30', $result['income']);
        $this->assertSame('0.40', $result['expense']);
        $this->assertSame('-0.10', $result['balance']);
    }

    /**
     * Los datos de otro espacio no influyen.
     */
    public function testSpaceIsolation(): void {
        [$space, $user] = $this->prepareSpace();
        [$other_space, $other_user, , $other_income] = $this->prepareSpace();

        $this->transaction(
            $other_space, $other_user, $other_income,
            'income', '999.99', '2026-10-10'
        );

        $result = (new ReportService())->getMonthly($space, $user, '2026-10');

        $this->assertSame('0.00', $result['income']);
        $this->assertSame(0, $result['transaction_count']);
    }

    /**
     * Un usuario ajeno no puede consultar el informe.
     */
    public function testOutsiderCannotReadReport(): void {
        [$space] = $this->prepareSpace();
        $outsider = $this->createUser();

        $this->expectException(DomainException::class);

        (new ReportService())->getMonthly($space, $outsider, '2026-10');
    }

    /**
     * Una membresía dada de baja no concede acceso.
     */
    public function testInactiveMemberCannotReadReport(): void {
        [$space] = $this->prepareSpace();
        $member = $this->createUser();

        $stmt = $this->db->prepare(
            'INSERT INTO `space_member` (id_space, id_user, left_at)
             VALUES (?, ?, NOW())'
        );
        $stmt->execute([$space, $member]);

        $this->expectException(DomainException::class);

        (new ReportService())->getMonthly($space, $member, '2026-10');
    }

    /**
     * Un miembro activo sí puede consultar el informe.
     */
    public function testActiveMemberCanReadReport(): void {
        [$space] = $this->prepareSpace();
        $member = $this->createUser();

        $stmt = $this->db->prepare(
            'INSERT INTO `space_member` (id_space, id_user)
             VALUES (?, ?)'
        );
        $stmt->execute([$space, $member]);

        $result = (new ReportService())->getMonthly($space, $member, '2026-10');

        $this->assertSame($space, $result['space_id']);
        $this->assertSame('0.00', $result['balance']);
    }

    /**
     * Comprueba formatos y meses inválidos.
     */
    public function testInvalidMonths(): void {
        [$space, $user] = $this->prepareSpace();
        $service = new ReportService();

        foreach ([
            '2026-00', '2026-13', '2026-1',
            '2026-10-01', 'octubre', '2026/10'
        ] as $month) {
            try {
                $service->getMonthly($space, $user, $month);
                $this->fail("Se ha aceptado el mes {$month}.");
            }
            catch (InvalidArgumentException) {
                $this->assertFalse($this->db->inTransaction());
            }
        }
    }

    /**
     * Las ocurrencias pendientes no afectan al balance.
     * Tras confirmarlas, el movimiento sí se contabiliza.
     */
    public function testRecurringIsCountedOnlyAfterConfirmation(): void {
        [$space, $user, $expense] = $this->prepareSpace();

        $stmt = $this->db->prepare(
            'INSERT INTO `recurring_transaction`
             (id_space, id_category, type, amount, concept,
              day_of_month, start_date, active)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $space, $expense, 'expense', '150.00',
            'Alquiler', 15, '2026-10-01', 1
        ]);

        $recurring_id = (int) $this->db->lastInsertId();

        $stmt = $this->db->prepare(
            'INSERT INTO `recurring_occurrence`
             (id_space, id_recurring_transaction, period,
              scheduled_date, status, id_category,
              type, concept, amount)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $space, $recurring_id, '2026-10-01',
            '2026-10-15', 'pending', $expense,
            'expense', 'Alquiler', '150.00'
        ]);

        $occurrence_id = (int) $this->db->lastInsertId();
        $report_service = new ReportService();

        $before = $report_service->getMonthly(
            $space, $user, '2026-10'
        );

        $this->assertSame('0.00', $before['expense']);
        $this->assertSame(0, $before['transaction_count']);

        (new \Osumi\OsumiFramework\App\Service\Recurring\OccurrenceService())
            ->resolve($space, $user, $occurrence_id, 'confirm');

        $after = $report_service->getMonthly(
            $space, $user, '2026-10'
        );

        $this->assertSame('150.00', $after['expense']);
        $this->assertSame('-150.00', $after['balance']);
        $this->assertSame(1, $after['transaction_count']);
    }


    /**
     * Agrupa movimientos por categoría directa sin
     * perder precisión decimal.
     */
    public function testMonthlyCategoryTotals(): void {
        [$space, $user, $expense, $income] = $this->prepareSpace();

        $this->transaction(
            $space, $user, $expense, 'expense', '0.10', '2026-10-01'
        );
        $this->transaction(
            $space, $user, $expense, 'expense', '0.20', '2026-10-15'
        );
        $this->transaction(
            $space, $user, $income, 'income', '1000.00', '2026-10-31'
        );
        $this->transaction(
            $space, $user, $expense, 'expense', '25.00', '2026-11-01'
        );

        $result = (new ReportService())->getMonthlyByCategory(
            $space, $user, '2026-10'
        );

        $this->assertSame($space, $result['space_id']);
        $this->assertSame('2026-10', $result['month']);
        $this->assertCount(2, $result['categories']);

        $by_id = [];
        foreach ($result['categories'] as $category) {
            $by_id[$category['category_id']] = $category;
        }

        $this->assertSame('0.30', $by_id[$expense]['amount']);
        $this->assertSame(2, $by_id[$expense]['transaction_count']);
        $this->assertSame('expense', $by_id[$expense]['type']);
        $this->assertSame('1000.00', $by_id[$income]['amount']);
        $this->assertSame(1, $by_id[$income]['transaction_count']);
        $this->assertSame('income', $by_id[$income]['type']);
    }

    /**
     * Una subcategoría no incrementa el importe directo
     * de la categoría padre.
     */
    public function testCategoryHierarchyDoesNotDoubleCount(): void {
        [$space, $user, $parent] = $this->prepareSpace();

        $child = (new CategoryService())->create(
            $space, $user, 'Supermercado', 'expense', $parent
        );

        $this->assertNotNull($child->id);

        $this->transaction(
            $space, $user, $parent, 'expense',
            '20.00', '2026-10-10'
        );
        $this->transaction(
            $space, $user, $child->id, 'expense',
            '35.00', '2026-10-11'
        );

        $result = (new ReportService())->getMonthlyByCategory(
            $space, $user, '2026-10'
        );

        $by_id = [];
        foreach ($result['categories'] as $category) {
            $by_id[$category['category_id']] = $category;
        }

        $this->assertSame('20.00', $by_id[$parent]['amount']);
        $this->assertNull($by_id[$parent]['parent_id']);
        $this->assertSame('35.00', $by_id[$child->id]['amount']);
        $this->assertSame($parent, $by_id[$child->id]['parent_id']);

        $monthly = (new ReportService())->getMonthly(
            $space, $user, '2026-10'
        );
        $this->assertSame('55.00', $monthly['expense']);
    }

    /**
     * Un mes vacío no inventa entradas por categoría.
     */
    public function testMonthlyCategoriesEmptyMonth(): void {
        [$space, $user] = $this->prepareSpace();

        $result = (new ReportService())->getMonthlyByCategory(
            $space, $user, '2026-10'
        );

        $this->assertSame([], $result['categories']);
    }

    /**
     * Las ocurrencias pendientes no se incluyen.
     */
    public function testPendingOccurrencesNotCountedByCategory(): void {
        [$space, $user, $expense] = $this->prepareSpace();

        $stmt = $this->db->prepare(
            'INSERT INTO `recurring_transaction`
             (id_space, id_category, type, amount, concept,
              day_of_month, start_date, active)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $space, $expense, 'expense', '150.00',
            'Alquiler', 15, '2026-10-01', 1
        ]);

        $recurring_id = (int) $this->db->lastInsertId();

        $stmt = $this->db->prepare(
            'INSERT INTO `recurring_occurrence`
             (id_space, id_recurring_transaction, period,
              scheduled_date, status, id_category,
              type, concept, amount)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $space, $recurring_id, '2026-10-01',
            '2026-10-15', 'pending', $expense,
            'expense', 'Alquiler', '150.00'
        ]);

        $occurrence_id = (int) $this->db->lastInsertId();

        $service = new ReportService();

        $this->assertSame(
            [],
            $service->getMonthlyByCategory(
                $space, $user, '2026-10'
            )['categories']
        );

        (new \Osumi\OsumiFramework\App\Service\Recurring\OccurrenceService())
            ->resolve($space, $user, $occurrence_id, 'confirm');

        $after = $service->getMonthlyByCategory(
            $space, $user, '2026-10'
        );

        $this->assertCount(1, $after['categories']);
        $this->assertSame('150.00', $after['categories'][0]['amount']);
        $this->assertSame(1, $after['categories'][0]['transaction_count']);
    }

    /**
     * El informe solo recoge datos del espacio solicitado.
     */
    public function testMonthlyCategoriesSpaceIsolation(): void {
        [$space, $user] = $this->prepareSpace();
        [$other_space, $other_user, $expense] = $this->prepareSpace();

        $this->transaction(
            $other_space, $other_user, $expense,
            'expense', '999.99', '2026-10-01'
        );

        $result = (new ReportService())->getMonthlyByCategory(
            $space, $user, '2026-10'
        );

        $this->assertSame([], $result['categories']);
    }

    /**
     * Un usuario ajeno no puede consultar el desglose.
     */
    public function testOutsiderCannotReadMonthlyCategories(): void {
        [$space] = $this->prepareSpace();
        $outsider = $this->createUser();

        $this->expectException(DomainException::class);

        (new ReportService())->getMonthlyByCategory(
            $space, $outsider, '2026-10'
        );
    }

    /**
     * Una membresía inactiva tampoco concede acceso.
     */
    public function testInactiveMemberCannotReadMonthlyCategories(): void {
        [$space] = $this->prepareSpace();
        $member = $this->createUser();

        $stmt = $this->db->prepare(
            'INSERT INTO `space_member`
             (id_space, id_user, left_at)
             VALUES (?, ?, NOW())'
        );
        $stmt->execute([$space, $member]);

        $this->expectException(DomainException::class);

        (new ReportService())->getMonthlyByCategory(
            $space, $member, '2026-10'
        );
    }

    /**
     * No admite meses con formato incorrecto.
     */
    public function testMonthlyCategoriesInvalidMonth(): void {
        [$space, $user] = $this->prepareSpace();

        $this->expectException(InvalidArgumentException::class);

        (new ReportService())->getMonthlyByCategory(
            $space, $user, '2026-13'
        );
    }

    /**
     * Comprueba una serie de meses consecutivos,
     * incluyendo un mes sin movimientos.
     */
    public function testEvolutionIncludesEmptyMonths(): void {
        [$space, $user, $expense, $income] = $this->prepareSpace();

        $this->transaction(
            $space, $user, $income, 'income',
            '2500.00', '2026-08-10'
        );
        $this->transaction(
            $space, $user, $expense, 'expense',
            '200.00', '2026-10-15'
        );

        $result = (new ReportService())->getEvolution(
            $space, $user, '2026-08', '2026-10'
        );

        $this->assertSame($space, $result['space_id']);
        $this->assertSame('2026-08', $result['month_from']);
        $this->assertSame('2026-10', $result['month_to']);
        $this->assertCount(3, $result['months']);

        $this->assertSame('2026-08', $result['months'][0]['month']);
        $this->assertSame('2500.00', $result['months'][0]['income']);
        $this->assertSame('2500.00', $result['months'][0]['balance']);

        $this->assertSame('2026-09', $result['months'][1]['month']);
        $this->assertSame('0.00', $result['months'][1]['income']);
        $this->assertSame('0.00', $result['months'][1]['expense']);
        $this->assertSame(0, $result['months'][1]['transaction_count']);

        $this->assertSame('2026-10', $result['months'][2]['month']);
        $this->assertSame('200.00', $result['months'][2]['expense']);
        $this->assertSame('-200.00', $result['months'][2]['balance']);
    }

    /**
     * Un rango de un único mes coincide con el informe mensual.
     */
    public function testEvolutionSingleMonthMatchesMonthlyReport(): void {
        [$space, $user, $expense, $income] = $this->prepareSpace();
        $service = new ReportService();

        $this->transaction(
            $space, $user, $income, 'income',
            '125.20', '2026-10-01'
        );
        $this->transaction(
            $space, $user, $expense, 'expense',
            '30.10', '2026-10-31'
        );

        $expected = $service->getMonthly(
            $space, $user, '2026-10'
        );

        $result = $service->getEvolution(
            $space, $user, '2026-10', '2026-10'
        );

        $this->assertCount(1, $result['months']);
        $this->assertSame($expected, $result['months'][0]);
        $this->assertSame('95.10', $result['months'][0]['balance']);
    }

    /**
     * Permite exactamente 24 mensualidades.
     */
    public function testEvolutionAllowsTwentyFourMonths(): void {
        [$space, $user] = $this->prepareSpace();

        $result = (new ReportService())->getEvolution(
            $space, $user, '2025-01', '2026-12'
        );

        $this->assertCount(24, $result['months']);
        $this->assertSame('2025-01', $result['months'][0]['month']);
        $this->assertSame('2026-12', $result['months'][23]['month']);
        $this->assertSame('0.00', $result['months'][23]['balance']);
    }

    /**
     * Rechaza rangos y formatos inválidos.
     */
    public function testEvolutionRejectsInvalidRanges(): void {
        [$space, $user] = $this->prepareSpace();
        $service = new ReportService();

        $cases = [
            ['2026-00', '2026-10'],
            ['2026-10', '2026-13'],
            ['2026-1', '2026-10'],
            ['2026-10-01', '2026-11'],
            ['2026-12', '2026-11'],
            ['2025-01', '2027-01'],
            ['octubre', '2026-10']
        ];

        foreach ($cases as [$from, $to]) {
            try {
                $service->getEvolution(
                    $space, $user, $from, $to
                );
                $this->fail(
                    "Se ha aceptado el rango {$from} - {$to}."
                );
            }
            catch (InvalidArgumentException) {
                $this->assertFalse($this->db->inTransaction());
            }
        }
    }

    /**
     * Evita mezclar movimientos entre espacios.
     */
    public function testEvolutionSpaceIsolation(): void {
        [$space, $user] = $this->prepareSpace();
        [$other_space, $other_user, , $other_income] =
            $this->prepareSpace();

        $this->transaction(
            $other_space, $other_user, $other_income,
            'income', '999.99', '2026-10-10'
        );

        $result = (new ReportService())->getEvolution(
            $space, $user, '2026-09', '2026-10'
        );

        foreach ($result['months'] as $month) {
            $this->assertSame('0.00', $month['income']);
            $this->assertSame('0.00', $month['balance']);
        }
    }

    /**
     * Los usuarios ajenos no pueden consultar la evolución.
     */
    public function testOutsiderCannotReadEvolution(): void {
        [$space] = $this->prepareSpace();
        $outsider = $this->createUser();

        $this->expectException(DomainException::class);

        (new ReportService())->getEvolution(
            $space, $outsider, '2026-09', '2026-10'
        );
    }

    /**
     * Una membresía inactiva no permite consultar la evolución.
     */
    public function testInactiveMemberCannotReadEvolution(): void {
        [$space] = $this->prepareSpace();
        $member = $this->createUser();

        $stmt = $this->db->prepare(
            'INSERT INTO `space_member`
             (id_space, id_user, left_at)
             VALUES (?, ?, NOW())'
        );
        $stmt->execute([$space, $member]);

        $this->expectException(DomainException::class);

        (new ReportService())->getEvolution(
            $space, $member, '2026-09', '2026-10'
        );
    }

    /**
     * Un miembro activo puede consultar la serie.
     */
    public function testActiveMemberCanReadEvolution(): void {
        [$space] = $this->prepareSpace();
        $member = $this->createUser();

        $stmt = $this->db->prepare(
            'INSERT INTO `space_member` (id_space, id_user)
             VALUES (?, ?)'
        );
        $stmt->execute([$space, $member]);

        $result = (new ReportService())->getEvolution(
            $space, $member, '2026-09', '2026-10'
        );

        $this->assertCount(2, $result['months']);
    }

    /**
     * Crea un espacio y dos categorías.
     *
     * @return array{int,int,int,int}
     */
    private function prepareSpace(): array {
        $user = $this->createUser();

        $space = (new SpaceService())->createSharedSpace(
            $user, 'Informe de integración'
        );
        $this->assertNotNull($space->id);
        $this->spaces[] = $space->id;

        $expense = (new CategoryService())->create(
            $space->id, $user, 'Compras', 'expense'
        );
        $income = (new CategoryService())->create(
            $space->id, $user, 'Salario', 'income'
        );

        $this->assertNotNull($expense->id);
        $this->assertNotNull($income->id);

        return [$space->id, $user, $expense->id, $income->id];
    }

    /**
     * Crea un movimiento real en el espacio.
     *
     * @param int $space Espacio.
     * @param int $user Usuario.
     * @param int $category Categoría.
     * @param string $type Tipo.
     * @param string $amount Importe.
     * @param string $date Fecha.
     * @return void
     */
    private function transaction(
        int $space,
        int $user,
        int $category,
        string $type,
        string $amount,
        string $date
    ): void {
        (new TransactionService())->create(
            $space, $user, $category, $type,
            $amount, null, $date
        );
    }

    /**
     * Crea un usuario único.
     *
     * @return int Identificador.
     */
    private function createUser(): int {
        $stmt = $this->db->prepare(
            'INSERT INTO `user` (`name`, `email`, `password_hash`)
             VALUES (?, ?, ?)'
        );

        $stmt->execute([
            'Usuario informe',
            'report-' . bin2hex(random_bytes(12)) . '@example.test',
            password_hash('contraseña segura', PASSWORD_DEFAULT)
        ]);

        $id = (int) $this->db->lastInsertId();
        $this->users[] = $id;

        return $id;
    }
}

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
     * Separa movimientos reales, pendientes y resueltos.
     *
     * Las confirmadas cuentan exclusivamente como
     * movimientos reales y las omitidas no cuentan.
     */
    public function testForecastSeparatesActualPendingAndResolved(): void {
        [$space, $user, $expense, $income] = $this->prepareSpace();

        $this->transaction(
            $space, $user, $income, 'income',
            '1000.00', '2026-10-01'
        );
        $this->transaction(
            $space, $user, $expense, 'expense',
            '100.00', '2026-10-02'
        );

        $this->createForecastOccurrence(
            $space, $income, 'income',
            '200.05', '2026-10-15'
        );
        $this->createForecastOccurrence(
            $space, $expense, 'expense',
            '300.10', '2026-10-16'
        );

        $skipped_id = $this->createForecastOccurrence(
            $space, $expense, 'expense',
            '25.00', '2026-10-17'
        );
        $confirmed_id = $this->createForecastOccurrence(
            $space, $expense, 'expense',
            '40.00', '2026-10-18'
        );

        $occurrence_service =
            new \Osumi\OsumiFramework\App\Service\Recurring\OccurrenceService();

        $occurrence_service->resolve(
            $space, $user, $skipped_id, 'skip'
        );
        $occurrence_service->resolve(
            $space, $user, $confirmed_id, 'confirm'
        );

        $result = (new ReportService())->getMonthlyForecast(
            $space, $user, '2026-10'
        );

        $this->assertSame($space, $result['space_id']);
        $this->assertSame('2026-10', $result['month']);

        // Real: ingreso manual y dos gastos,
        // uno manual y otro recurrente confirmado.
        $this->assertSame('1000.00', $result['actual']['income']);
        $this->assertSame('140.00', $result['actual']['expense']);
        $this->assertSame('860.00', $result['actual']['balance']);
        $this->assertSame(3, $result['actual']['transaction_count']);

        // Pendiente: solo las dos ocurrencias no resueltas.
        $this->assertSame('200.05', $result['pending_income']);
        $this->assertSame('300.10', $result['pending_expense']);
        $this->assertSame('-100.05', $result['pending_balance']);
        $this->assertSame(1, $result['pending_income_count']);
        $this->assertSame(1, $result['pending_expense_count']);
        $this->assertSame(2, $result['pending_occurrence_count']);

        // Proyección: real + pendiente, con precisión exacta.
        $this->assertSame('1200.05', $result['projected_income']);
        $this->assertSame('440.10', $result['projected_expense']);
        $this->assertSame('759.95', $result['projected_balance']);
    }

    /**
     * Confirmar una pendiente la traslada a la contabilidad
     * real sin duplicar el resultado proyectado.
     */
    public function testForecastAfterConfirmation(): void {
        [$space, $user, $expense, $income] = $this->prepareSpace();

        $this->transaction(
            $space, $user, $income, 'income',
            '250.00', '2026-10-01'
        );

        $occurrence_id = $this->createForecastOccurrence(
            $space, $expense, 'expense',
            '100.00', '2026-10-15'
        );

        $service = new ReportService();

        $before = $service->getMonthlyForecast(
            $space, $user, '2026-10'
        );

        $this->assertSame('250.00', $before['actual']['balance']);
        $this->assertSame('100.00', $before['pending_expense']);
        $this->assertSame('150.00', $before['projected_balance']);

        (new \Osumi\OsumiFramework\App\Service\Recurring\OccurrenceService())
            ->resolve($space, $user, $occurrence_id, 'confirm');

        $after = $service->getMonthlyForecast(
            $space, $user, '2026-10'
        );

        $this->assertSame('100.00', $after['actual']['expense']);
        $this->assertSame('150.00', $after['actual']['balance']);
        $this->assertSame('0.00', $after['pending_expense']);
        $this->assertSame(0, $after['pending_occurrence_count']);
        $this->assertSame('150.00', $after['projected_balance']);
        $this->assertSame(2, $after['actual']['transaction_count']);
    }

    /**
     * Omitir una pendiente elimina su efecto en la
     * previsión sin crear ningún movimiento.
     */
    public function testForecastAfterSkipping(): void {
        [$space, $user, $expense] = $this->prepareSpace();

        $occurrence_id = $this->createForecastOccurrence(
            $space, $expense, 'expense',
            '125.00', '2026-10-15'
        );

        $service = new ReportService();
        $before = $service->getMonthlyForecast(
            $space, $user, '2026-10'
        );

        $this->assertSame('-125.00', $before['projected_balance']);

        (new \Osumi\OsumiFramework\App\Service\Recurring\OccurrenceService())
            ->resolve($space, $user, $occurrence_id, 'skip');

        $after = $service->getMonthlyForecast(
            $space, $user, '2026-10'
        );

        $this->assertSame('0.00', $after['actual']['balance']);
        $this->assertSame('0.00', $after['pending_balance']);
        $this->assertSame('0.00', $after['projected_balance']);
        $this->assertSame(0, $after['actual']['transaction_count']);
        $this->assertSame(0, $after['pending_occurrence_count']);
    }

    /**
     * Comprueba sumas decimales sin coma flotante.
     */
    public function testForecastDecimalPrecision(): void {
        [$space, $user, $expense, $income] = $this->prepareSpace();

        $this->transaction(
            $space, $user, $income, 'income',
            '0.10', '2026-10-01'
        );
        $this->transaction(
            $space, $user, $expense, 'expense',
            '0.40', '2026-10-02'
        );

        $this->createForecastOccurrence(
            $space, $income, 'income',
            '0.20', '2026-10-15'
        );
        $this->createForecastOccurrence(
            $space, $expense, 'expense',
            '0.01', '2026-10-16'
        );

        $result = (new ReportService())->getMonthlyForecast(
            $space, $user, '2026-10'
        );

        $this->assertSame('0.30', $result['projected_income']);
        $this->assertSame('0.41', $result['projected_expense']);
        $this->assertSame('-0.11', $result['projected_balance']);
    }

    /**
     * Un mes sin movimientos ni ocurrencias devuelve ceros.
     */
    public function testForecastEmptyMonth(): void {
        [$space, $user] = $this->prepareSpace();

        $result = (new ReportService())->getMonthlyForecast(
            $space, $user, '2026-10'
        );

        $this->assertSame('0.00', $result['actual']['balance']);
        $this->assertSame('0.00', $result['pending_income']);
        $this->assertSame('0.00', $result['pending_expense']);
        $this->assertSame('0.00', $result['pending_balance']);
        $this->assertSame('0.00', $result['projected_balance']);
        $this->assertSame(0, $result['pending_occurrence_count']);
    }

    /**
     * El informe solo considera las fechas del mes solicitado.
     */
    public function testForecastFiltersByScheduledMonth(): void {
        [$space, $user, $expense, $income] = $this->prepareSpace();

        $this->transaction(
            $space, $user, $income, 'income',
            '200.00', '2026-11-01'
        );

        $this->createForecastOccurrence(
            $space, $expense, 'expense',
            '100.00', '2026-11-15'
        );

        $october = (new ReportService())->getMonthlyForecast(
            $space, $user, '2026-10'
        );

        $this->assertSame('0.00', $october['projected_balance']);
        $this->assertSame(0, $october['pending_occurrence_count']);

        $november = (new ReportService())->getMonthlyForecast(
            $space, $user, '2026-11'
        );

        $this->assertSame('100.00', $november['projected_balance']);
        $this->assertSame(1, $november['pending_occurrence_count']);
    }

    /**
     * Una plantilla desactivada mantiene sus pendientes
     * previamente creadas dentro de la previsión.
     */
    public function testForecastIncludesPendingFromInactiveTemplate(): void {
        [$space, $user, $expense] = $this->prepareSpace();

        $occurrence_id = $this->createForecastOccurrence(
            $space, $expense, 'expense',
            '80.00', '2026-10-15'
        );

        $stmt = $this->db->prepare(
            'UPDATE `recurring_transaction`
             SET active = 0
             WHERE id = (
                SELECT id_recurring_transaction
                FROM `recurring_occurrence`
                WHERE id = ?
             )'
        );
        $stmt->execute([$occurrence_id]);

        $result = (new ReportService())->getMonthlyForecast(
            $space, $user, '2026-10'
        );

        $this->assertSame('80.00', $result['pending_expense']);
        $this->assertSame('-80.00', $result['projected_balance']);
    }

    /**
     * No se contabilizan importes de otros espacios.
     */
    public function testForecastSpaceIsolation(): void {
        [$space, $user] = $this->prepareSpace();
        [$other_space, $other_user, $other_expense, $other_income] =
            $this->prepareSpace();

        $this->transaction(
            $other_space, $other_user, $other_income,
            'income', '999.00', '2026-10-01'
        );

        $this->createForecastOccurrence(
            $other_space, $other_expense, 'expense',
            '500.00', '2026-10-15'
        );

        $result = (new ReportService())->getMonthlyForecast(
            $space, $user, '2026-10'
        );

        $this->assertSame('0.00', $result['actual']['balance']);
        $this->assertSame('0.00', $result['pending_balance']);
        $this->assertSame('0.00', $result['projected_balance']);
    }

    /**
     * Un usuario ajeno no puede consultar previsiones.
     */
    public function testOutsiderCannotReadForecast(): void {
        [$space] = $this->prepareSpace();
        $outsider = $this->createUser();

        $this->expectException(DomainException::class);

        (new ReportService())->getMonthlyForecast(
            $space, $outsider, '2026-10'
        );
    }

    /**
     * Un miembro dado de baja no puede consultar previsiones.
     */
    public function testInactiveMemberCannotReadForecast(): void {
        [$space] = $this->prepareSpace();
        $member = $this->createUser();

        $stmt = $this->db->prepare(
            'INSERT INTO `space_member`
             (id_space, id_user, left_at)
             VALUES (?, ?, NOW())'
        );
        $stmt->execute([$space, $member]);

        $this->expectException(DomainException::class);

        (new ReportService())->getMonthlyForecast(
            $space, $member, '2026-10'
        );
    }

    /**
     * Comprueba que la validación del mes se reutiliza.
     */
    public function testForecastInvalidMonth(): void {
        [$space, $user] = $this->prepareSpace();

        $this->expectException(InvalidArgumentException::class);

        (new ReportService())->getMonthlyForecast(
            $space, $user, '2026-13'
        );
    }

    /**
     * Consultar una previsión no genera mensualidades
     * automáticamente a partir de plantillas.
     */
    public function testForecastDoesNotGenerateOccurrences(): void {
        [$space, $user, $expense] = $this->prepareSpace();

        $stmt = $this->db->prepare(
            'INSERT INTO `recurring_transaction`
             (id_space, id_category, type, amount,
              concept, day_of_month, start_date, active)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $space, $expense, 'expense', '300.00',
            'Suscripción', 15, '2026-10-01', 1
        ]);

        $result = (new ReportService())->getMonthlyForecast(
            $space, $user, '2026-10'
        );

        $this->assertSame(0, $result['pending_occurrence_count']);
        $this->assertSame('0.00', $result['projected_expense']);

        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM `recurring_occurrence`
             WHERE id_space = ?'
        );
        $stmt->execute([$space]);

        $this->assertSame(0, (int) $stmt->fetchColumn());
    }

    /**
     * Devuelve todos los meses del rango, incluidos
     * los vacíos, con resultados reales y previstos.
     */
    public function testForecastEvolutionIncludesEmptyMonths(): void {
        [$space, $user, $expense, $income] =
            $this->prepareSpace();

        $this->transaction(
            $space, $user, $income, 'income',
            '1000.00', '2026-10-01'
        );

        $this->createForecastOccurrence(
            $space, $expense, 'expense',
            '125.50', '2026-10-15'
        );

        $this->createForecastOccurrence(
            $space, $income, 'income',
            '200.00', '2026-11-10'
        );

        $result = (new ReportService())->getForecastEvolution(
            $space, $user, '2026-09', '2026-11'
        );

        $this->assertSame($space, $result['space_id']);
        $this->assertSame('2026-09', $result['month_from']);
        $this->assertSame('2026-11', $result['month_to']);
        $this->assertCount(3, $result['months']);

        $september = $result['months'][0];
        $this->assertSame('2026-09', $september['month']);
        $this->assertSame('0.00', $september['actual']['balance']);
        $this->assertSame('0.00', $september['pending_balance']);
        $this->assertSame('0.00', $september['projected_balance']);
        $this->assertSame(0, $september['pending_occurrence_count']);

        $october = $result['months'][1];
        $this->assertSame('2026-10', $october['month']);
        $this->assertSame('1000.00', $october['actual']['income']);
        $this->assertSame('125.50', $october['pending_expense']);
        $this->assertSame('874.50', $october['projected_balance']);
        $this->assertSame(1, $october['pending_occurrence_count']);

        $november = $result['months'][2];
        $this->assertSame('2026-11', $november['month']);
        $this->assertSame('0.00', $november['actual']['balance']);
        $this->assertSame('200.00', $november['pending_income']);
        $this->assertSame('200.00', $november['projected_balance']);
    }

    /**
     * Cada punto de la serie conserva exactamente el
     * contrato de la previsión mensual individual.
     */
    public function testForecastEvolutionMatchesMonthlyForecast(): void {
        [$space, $user, $expense, $income] =
            $this->prepareSpace();

        $this->transaction(
            $space, $user, $income, 'income',
            '0.10', '2026-10-01'
        );

        $this->createForecastOccurrence(
            $space, $income, 'income',
            '0.20', '2026-10-15'
        );

        $this->createForecastOccurrence(
            $space, $expense, 'expense',
            '0.40', '2026-10-16'
        );

        $service = new ReportService();

        $individual = $service->getMonthlyForecast(
            $space, $user, '2026-10'
        );

        $evolution = $service->getForecastEvolution(
            $space, $user, '2026-10', '2026-10'
        );

        $this->assertCount(1, $evolution['months']);
        $this->assertSame(
            $individual,
            $evolution['months'][0]
        );

        $this->assertSame(
            '0.30',
            $evolution['months'][0]['projected_income']
        );
        $this->assertSame(
            '0.40',
            $evolution['months'][0]['projected_expense']
        );
        $this->assertSame(
            '-0.10',
            $evolution['months'][0]['projected_balance']
        );
    }

    /**
     * Comprueba que la serie atraviesa correctamente
     * el cambio de año.
     */
    public function testForecastEvolutionCrossesYearBoundary(): void {
        [$space, $user] = $this->prepareSpace();

        $result = (new ReportService())->getForecastEvolution(
            $space, $user, '2026-12', '2027-02'
        );

        $this->assertSame(
            ['2026-12', '2027-01', '2027-02'],
            array_column($result['months'], 'month')
        );

        foreach ($result['months'] as $month) {
            $this->assertSame(
                '0.00',
                $month['projected_balance']
            );
        }
    }

    /**
     * Una confirmación mueve la cantidad a los datos
     * reales sin duplicar el resultado proyectado.
     */
    public function testForecastEvolutionAfterConfirmation(): void {
        [$space, $user, $expense, $income] =
            $this->prepareSpace();

        $this->transaction(
            $space, $user, $income, 'income',
            '300.00', '2026-10-01'
        );

        $occurrence_id = $this->createForecastOccurrence(
            $space, $expense, 'expense',
            '100.00', '2026-10-15'
        );

        $service = new ReportService();

        $before = $service->getForecastEvolution(
            $space, $user, '2026-10', '2026-11'
        );

        $this->assertSame(
            '200.00',
            $before['months'][0]['projected_balance']
        );

        (new \Osumi\OsumiFramework\App\Service\Recurring\OccurrenceService())
            ->resolve($space, $user, $occurrence_id, 'confirm');

        $after = $service->getForecastEvolution(
            $space, $user, '2026-10', '2026-11'
        );

        $this->assertSame(
            '100.00',
            $after['months'][0]['actual']['expense']
        );
        $this->assertSame(
            '0.00',
            $after['months'][0]['pending_expense']
        );
        $this->assertSame(
            '200.00',
            $after['months'][0]['projected_balance']
        );
        $this->assertSame(
            2,
            $after['months'][0]['actual']['transaction_count']
        );
        $this->assertSame(
            0,
            $after['months'][0]['pending_occurrence_count']
        );

        $this->assertSame(
            '0.00',
            $after['months'][1]['projected_balance']
        );
    }

    /**
     * Omitir una ocurrencia la elimina de la previsión
     * sin generar un movimiento real.
     */
    public function testForecastEvolutionAfterSkipping(): void {
        [$space, $user, $expense] = $this->prepareSpace();

        $occurrence_id = $this->createForecastOccurrence(
            $space, $expense, 'expense',
            '75.00', '2026-11-15'
        );

        $service = new ReportService();

        $before = $service->getForecastEvolution(
            $space, $user, '2026-10', '2026-11'
        );

        $this->assertSame(
            '-75.00',
            $before['months'][1]['projected_balance']
        );

        (new \Osumi\OsumiFramework\App\Service\Recurring\OccurrenceService())
            ->resolve($space, $user, $occurrence_id, 'skip');

        $after = $service->getForecastEvolution(
            $space, $user, '2026-10', '2026-11'
        );

        $this->assertSame(
            '0.00',
            $after['months'][1]['projected_balance']
        );
        $this->assertSame(
            0,
            $after['months'][1]['actual']['transaction_count']
        );
        $this->assertSame(
            0,
            $after['months'][1]['pending_occurrence_count']
        );
    }

    /**
     * Permite exactamente 24 meses, ambos incluidos.
     */
    public function testForecastEvolutionAllowsTwentyFourMonths(): void {
        [$space, $user] = $this->prepareSpace();

        $result = (new ReportService())->getForecastEvolution(
            $space, $user, '2025-01', '2026-12'
        );

        $this->assertCount(24, $result['months']);
        $this->assertSame(
            '2025-01',
            $result['months'][0]['month']
        );
        $this->assertSame(
            '2026-12',
            $result['months'][23]['month']
        );
        $this->assertFalse($this->db->inTransaction());
    }

    /**
     * Rechaza fechas inválidas, rangos invertidos
     * e intervalos superiores a 24 meses.
     */
    public function testForecastEvolutionRejectsInvalidRanges(): void {
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
                $service->getForecastEvolution(
                    $space, $user, $from, $to
                );

                $this->fail(
                    "Se ha aceptado el rango {$from} - {$to}."
                );
            }
            catch (InvalidArgumentException) {
                $this->assertFalse(
                    $this->db->inTransaction()
                );
            }
        }
    }

    /**
     * Rechaza identificadores inválidos.
     */
    public function testForecastEvolutionRejectsInvalidIds(): void {
        $this->expectException(InvalidArgumentException::class);

        (new ReportService())->getForecastEvolution(
            0, 1, '2026-10', '2026-11'
        );
    }

    /**
     * No mezcla las previsiones de dos espacios.
     */
    public function testForecastEvolutionIsolatedBySpace(): void {
        [$space, $user] = $this->prepareSpace();

        [$other_space, $other_user, $expense, $income] =
            $this->prepareSpace();

        $this->transaction(
            $other_space, $other_user, $income,
            'income', '999.00', '2026-10-01'
        );

        $this->createForecastOccurrence(
            $other_space, $expense, 'expense',
            '125.00', '2026-11-15'
        );

        $result = (new ReportService())->getForecastEvolution(
            $space, $user, '2026-10', '2026-11'
        );

        foreach ($result['months'] as $month) {
            $this->assertSame(
                '0.00',
                $month['actual']['balance']
            );
            $this->assertSame(
                '0.00',
                $month['pending_balance']
            );
            $this->assertSame(
                '0.00',
                $month['projected_balance']
            );
        }
    }

    /**
     * Un usuario ajeno no puede consultar previsiones.
     */
    public function testOutsiderCannotReadForecastEvolution(): void {
        [$space] = $this->prepareSpace();
        $outsider = $this->createUser();

        $this->expectException(DomainException::class);

        (new ReportService())->getForecastEvolution(
            $space, $outsider, '2026-10', '2026-11'
        );
    }

    /**
     * Una membresía inactiva no concede acceso.
     */
    public function testInactiveMemberCannotReadForecastEvolution(): void {
        [$space] = $this->prepareSpace();
        $member = $this->createUser();

        $stmt = $this->db->prepare(
            'INSERT INTO `space_member`
             (id_space, id_user, left_at)
             VALUES (?, ?, NOW())'
        );
        $stmt->execute([$space, $member]);

        $this->expectException(DomainException::class);

        (new ReportService())->getForecastEvolution(
            $space, $member, '2026-10', '2026-11'
        );
    }

    /**
     * La consulta de evolución no genera ocurrencias
     * a partir de las plantillas activas.
     */
    public function testForecastEvolutionDoesNotGenerateOccurrences(): void {
        [$space, $user, $expense] = $this->prepareSpace();

        $stmt = $this->db->prepare(
            'INSERT INTO `recurring_transaction`
             (id_space, id_category, type, amount,
              concept, day_of_month, start_date, active)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );

        $stmt->execute([
            $space, $expense, 'expense', '300.00',
            'Suscripción', 15, '2026-10-01', 1
        ]);

        $result = (new ReportService())->getForecastEvolution(
            $space, $user, '2026-10', '2026-12'
        );

        $this->assertCount(3, $result['months']);

        foreach ($result['months'] as $month) {
            $this->assertSame(
                '0.00',
                $month['projected_expense']
            );
            $this->assertSame(
                0,
                $month['pending_occurrence_count']
            );
        }

        $stmt = $this->db->prepare(
            'SELECT COUNT(*)
             FROM `recurring_occurrence`
             WHERE id_space = ?'
        );
        $stmt->execute([$space]);

        $this->assertSame(0, (int) $stmt->fetchColumn());
    }

    /**
     * Crea una plantilla y una ocurrencia pendiente de prueba.
     *
     * Utiliza SQL sobre la conexión de integración para
     * preparar fechas fijas, sin depender del mes actual
     * ni de las reglas de generación retrospectiva.
     *
     * @param int $space_id Espacio contable.
     * @param int $category_id Categoría.
     * @param string $type Tipo económico.
     * @param string $amount Importe decimal.
     * @param string $scheduled_date Fecha programada.
     * @return int Identificador de la ocurrencia.
     */
    private function createForecastOccurrence(
        int $space_id,
        int $category_id,
        string $type,
        string $amount,
        string $scheduled_date
    ): int {
        $period = substr($scheduled_date, 0, 7) . '-01';
        $day = (int) substr($scheduled_date, 8, 2);

        $stmt = $this->db->prepare(
            'INSERT INTO `recurring_transaction`
             (id_space, id_category, type, amount, concept,
              day_of_month, start_date, active)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );

        $stmt->execute([
            $space_id,
            $category_id,
            $type,
            $amount,
            'Previsión de prueba',
            $day,
            $period,
            1
        ]);

        $recurring_id = (int) $this->db->lastInsertId();

        $stmt = $this->db->prepare(
            'INSERT INTO `recurring_occurrence`
             (id_space, id_recurring_transaction,
              period, scheduled_date, status,
              id_category, type, concept, amount)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );

        $stmt->execute([
            $space_id,
            $recurring_id,
            $period,
            $scheduled_date,
            'pending',
            $category_id,
            $type,
            'Previsión de prueba',
            $amount
        ]);

        return (int) $this->db->lastInsertId();
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

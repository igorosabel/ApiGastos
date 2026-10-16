<?php declare(strict_types=1);

require_once dirname(__DIR__) . '/OrmIntegrationTestCase.php';

use Osumi\OsumiFramework\App\Service\Category\CategoryService;
use Osumi\OsumiFramework\App\Service\Recurring\OccurrenceService;
use Osumi\OsumiFramework\App\Service\Recurring\RecurringService;
use Osumi\OsumiFramework\App\Service\Recurring\SpaceOccurrenceService;
use Osumi\OsumiFramework\App\Service\Space\SpaceService;

final class SpaceOccurrenceServiceIntegrationTest extends OrmIntegrationTestCase {
    /** @var list<int> */
    private array $users = [];

    /** @var list<int> */
    private array $spaces = [];

    /**
     * Limpia exclusivamente los datos creados por cada test.
     */
    protected function tearDown(): void {
        try {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            foreach (array_reverse($this->spaces) as $space_id) {
                foreach ([
                    'recurring_occurrence',
                    'recurring_transaction',
                    'financial_transaction',
                    'category',
                    'space_member'
                ] as $table) {
                    $stmt = $this->db->prepare(
                        "DELETE FROM `{$table}` WHERE id_space = ?"
                    );
                    $stmt->execute([$space_id]);
                }

                $stmt = $this->db->prepare(
                    'DELETE FROM `space` WHERE id = ?'
                );
                $stmt->execute([$space_id]);
            }

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
     * Genera las ocurrencias de varias plantillas activas.
     */
    public function testGenerateAllActiveTemplates(): void {
        [$space_id, $owner_id, $category_id] = $this->prepareSpace();
        $period = $this->currentPeriod();

        $this->createRecurring(
            $space_id, $owner_id, $category_id,
            'Alquiler', 5, $period
        );

        $this->createRecurring(
            $space_id, $owner_id, $category_id,
            'Suscripción', 15, $period
        );

        $service = new SpaceOccurrenceService();
        $result = $service->generate($space_id, $owner_id, $period);

        $this->assertSame([
            'created' => 2,
            'existing' => 0,
            'skipped' => 0,
            'total' => 2
        ], $result);

        $this->assertSame(2, $this->countOccurrences($space_id));
        $this->assertSame(0, $this->countTransactions($space_id));
    }

    /**
     * Dos ejecuciones no crean duplicados.
     */
    public function testBulkGenerationIsIdempotent(): void {
        [$space_id, $owner_id, $category_id] = $this->prepareSpace();
        $period = $this->currentPeriod();

        $this->createRecurring(
            $space_id, $owner_id, $category_id,
            'Alquiler', 10, $period
        );

        $service = new SpaceOccurrenceService();

        $first = $service->generate($space_id, $owner_id, $period);
        $second = $service->generate($space_id, $owner_id, $period);

        $this->assertSame(1, $first['created']);
        $this->assertSame(0, $second['created']);
        $this->assertSame(1, $second['existing']);
        $this->assertSame(1, $this->countOccurrences($space_id));
    }

    /**
     * Ignora las plantillas inactivas.
     */
    public function testInactiveTemplatesAreNotEvaluated(): void {
        [$space_id, $owner_id, $category_id] = $this->prepareSpace();
        $period = $this->currentPeriod();

        $active = $this->createRecurring(
            $space_id, $owner_id, $category_id,
            'Activa', 10, $period
        );

        $inactive = $this->createRecurring(
            $space_id, $owner_id, $category_id,
            'Inactiva', 15, $period
        );

        (new RecurringService())->setActive(
            $space_id, $owner_id, $inactive->id, false
        );

        $result = (new SpaceOccurrenceService())->generate(
            $space_id, $owner_id, $period
        );

        $this->assertSame(1, $result['created']);
        $this->assertSame(1, $result['total']);
        $this->assertSame(1, $this->countOccurrences($space_id));
        $this->assertNotNull($active->id);
    }

    /**
     * Las fechas incompatibles cuentan como omitidas.
     */
    public function testOutOfRangeTemplatesAreSkipped(): void {
        [$space_id, $owner_id, $category_id] = $this->prepareSpace();
        $period = $this->currentPeriod();

        $next_period = (new DateTimeImmutable($period))
            ->modify('+1 month')
            ->format('Y-m-d');

        $this->createRecurring(
            $space_id, $owner_id, $category_id,
            'Futura', 10, $next_period
        );

        $result = (new SpaceOccurrenceService())->generate(
            $space_id, $owner_id, $period
        );

        $this->assertSame(0, $result['created']);
        $this->assertSame(0, $result['existing']);
        $this->assertSame(1, $result['skipped']);
        $this->assertSame(1, $result['total']);
    }

    /**
     * Una mensualidad resuelta permanece intacta.
     */
    public function testPreviouslyResolvedOccurrenceRemainsUntouched(): void {
        [$space_id, $owner_id, $category_id] = $this->prepareSpace();
        $period = $this->currentPeriod();

        $recurring = $this->createRecurring(
            $space_id, $owner_id, $category_id,
            'Cuota', 10, $period
        );

        $service = new SpaceOccurrenceService();
        $service->generate($space_id, $owner_id, $period);

        $stmt = $this->db->prepare(
            'SELECT id FROM `recurring_occurrence`
             WHERE id_recurring_transaction = ?'
        );
        $stmt->execute([$recurring->id]);
        $occurrence_id = (int) $stmt->fetchColumn();

        (new OccurrenceService())->resolve(
            $space_id, $owner_id, $occurrence_id, 'confirm'
        );

        $result = $service->generate($space_id, $owner_id, $period);

        $this->assertSame(0, $result['created']);
        $this->assertSame(1, $result['existing']);
        $this->assertSame(1, $this->countTransactions($space_id));

        $stmt = $this->db->prepare(
            'SELECT status FROM `recurring_occurrence`
             WHERE id = ?'
        );
        $stmt->execute([$occurrence_id]);

        $this->assertSame('confirmed', $stmt->fetchColumn());
    }

    /**
     * No mezcla plantillas de diferentes espacios.
     */
    public function testDoesNotMixSpaces(): void {
        [$space_id, $owner_id, $category_id] = $this->prepareSpace();
        [$other_space_id, $other_owner_id, $other_category_id] =
            $this->prepareSpace();

        $period = $this->currentPeriod();

        $this->createRecurring(
            $space_id, $owner_id, $category_id,
            'Primera', 10, $period
        );

        $this->createRecurring(
            $other_space_id, $other_owner_id,
            $other_category_id, 'Segunda', 15, $period
        );

        (new SpaceOccurrenceService())->generate(
            $space_id, $owner_id, $period
        );

        $this->assertSame(1, $this->countOccurrences($space_id));
        $this->assertSame(0, $this->countOccurrences($other_space_id));
    }

    /**
     * Un usuario ajeno no puede generar para el espacio.
     */
    public function testOutsiderCannotGenerate(): void {
        [$space_id] = $this->prepareSpace();
        $outsider = $this->createUser();

        $this->expectException(DomainException::class);

        (new SpaceOccurrenceService())->generate(
            $space_id, $outsider, $this->currentPeriod()
        );
    }

    /**
     * Un miembro dado de baja tampoco puede generar.
     */
    public function testInactiveMemberCannotGenerate(): void {
        [$space_id] = $this->prepareSpace();
        $member_id = $this->createUser();

        $stmt = $this->db->prepare(
            'INSERT INTO `space_member`
             (id_space, id_user, left_at)
             VALUES (?, ?, NOW())'
        );
        $stmt->execute([$space_id, $member_id]);

        $this->expectException(DomainException::class);

        (new SpaceOccurrenceService())->generate(
            $space_id, $member_id, $this->currentPeriod()
        );
    }

    /**
     * Rechaza un periodo que no empiece en día 1.
     */
    public function testInvalidPeriod(): void {
        $this->expectException(InvalidArgumentException::class);

        (new SpaceOccurrenceService())->generate(
            1, 1, '2026-10-15'
        );
    }

    /**
     * Una desactivación impide generar nuevas ocurrencias.
     * Tras reactivar, un mes válido pendiente puede crearse.
     */
    public function testReactivationAllowsMissingOccurrence(): void {
        [$space_id, $owner_id, $category_id] =
            $this->prepareSpace();

        $period = $this->currentPeriod();

        $recurring = $this->createRecurring(
            $space_id,
            $owner_id,
            $category_id,
            'Suscripción',
            10,
            $period
        );

        $recurring_service = new RecurringService();
        $generation_service = new SpaceOccurrenceService();

        $recurring_service->setActive(
            $space_id, $owner_id, $recurring->id, false
        );

        $inactive = $generation_service->generate(
            $space_id, $owner_id, $period
        );

        $this->assertSame([
            'created' => 0,
            'existing' => 0,
            'skipped' => 0,
            'total' => 0
        ], $inactive);

        $this->assertSame(0, $this->countOccurrences($space_id));

        $recurring_service->setActive(
            $space_id, $owner_id, $recurring->id, true
        );

        $reactivated = $generation_service->generate(
            $space_id, $owner_id, $period
        );

        $this->assertSame([
            'created' => 1,
            'existing' => 0,
            'skipped' => 0,
            'total' => 1
        ], $reactivated);

        $repeated = $generation_service->generate(
            $space_id, $owner_id, $period
        );

        $this->assertSame(0, $repeated['created']);
        $this->assertSame(1, $repeated['existing']);
        $this->assertSame(1, $this->countOccurrences($space_id));
        $this->assertSame(0, $this->countTransactions($space_id));
    }

    /**
     * Los cambios hechos durante una pausa afectan a
     * mensualidades nuevas, pero no a las ya existentes.
     */
    public function testExistingSnapshotSurvivesPauseAndUpdate(): void {
        [$space_id, $owner_id, $category_id] =
            $this->prepareSpace();

        $period = $this->currentPeriod();
        $next_period = (new DateTimeImmutable($period))
            ->modify('+1 month')
            ->format('Y-m-d');

        $recurring = $this->createRecurring(
            $space_id,
            $owner_id,
            $category_id,
            'Cuota original',
            10,
            $period
        );

        $occurrence_service = new OccurrenceService();
        $recurring_service = new RecurringService();
        $generation_service = new SpaceOccurrenceService();

        $first = $occurrence_service->generate(
            $space_id, $owner_id, $recurring->id, $period
        );

        $recurring_service->setActive(
            $space_id, $owner_id, $recurring->id, false
        );

        $recurring_service->update(
            $space_id,
            $owner_id,
            $recurring->id,
            $category_id,
            'expense',
            '80.00',
            'Cuota actualizada',
            20,
            $next_period
        );

        // La instantánea existente puede recuperarse
        // incluso con la plantilla desactivada.
        $existing = $occurrence_service->generate(
            $space_id, $owner_id, $recurring->id, $period
        );

        $this->assertFalse($existing['created']);
        $this->assertSame(
            $first['occurrence']->id,
            $existing['occurrence']->id
        );
        $this->assertSame(
            '50.00',
            $existing['occurrence']->amount
        );
        $this->assertSame(
            'Cuota original',
            $existing['occurrence']->concept
        );
        $this->assertSame(
            substr($period, 0, 8) . '10',
            $existing['occurrence']->scheduled_date
        );

        // El lote no incluye plantillas inactivas.
        $inactive = $generation_service->generate(
            $space_id, $owner_id, $period
        );
        $this->assertSame(0, $inactive['total']);

        $recurring_service->setActive(
            $space_id, $owner_id, $recurring->id, true
        );

        // Aunque la nueva fecha de inicio excluya este mes,
        // la ocurrencia histórica existente se conserva.
        $repeated = $generation_service->generate(
            $space_id, $owner_id, $period
        );

        $this->assertSame(0, $repeated['created']);
        $this->assertSame(1, $repeated['existing']);

        // El mes siguiente sí utiliza los valores editados.
        $next = $generation_service->generate(
            $space_id, $owner_id, $next_period
        );

        $this->assertSame(1, $next['created']);

        $new_occurrence = $occurrence_service->generate(
            $space_id, $owner_id, $recurring->id, $next_period
        );

        $this->assertFalse($new_occurrence['created']);
        $this->assertSame(
            '80.00',
            $new_occurrence['occurrence']->amount
        );
        $this->assertSame(
            'Cuota actualizada',
            $new_occurrence['occurrence']->concept
        );
        $this->assertSame(
            substr($next_period, 0, 8) . '20',
            $new_occurrence['occurrence']->scheduled_date
        );

        $this->assertSame(2, $this->countOccurrences($space_id));
        $this->assertSame(0, $this->countTransactions($space_id));
    }

    /**
     * Desactivar y reactivar no reabre ocurrencias
     * confirmadas ni omitidas.
     */
    public function testResolvedOccurrencesSurviveReactivation(): void {
        [$space_id, $owner_id, $category_id] =
            $this->prepareSpace();

        $period = $this->currentPeriod();

        $confirmed_template = $this->createRecurring(
            $space_id, $owner_id, $category_id,
            'Confirmada', 10, $period
        );

        $skipped_template = $this->createRecurring(
            $space_id, $owner_id, $category_id,
            'Omitida', 15, $period
        );

        $occurrence_service = new OccurrenceService();
        $recurring_service = new RecurringService();
        $generation_service = new SpaceOccurrenceService();

        $first = $generation_service->generate(
            $space_id, $owner_id, $period
        );

        $this->assertSame(2, $first['created']);

        $confirmed = $occurrence_service->generate(
            $space_id, $owner_id,
            $confirmed_template->id, $period
        );

        $skipped = $occurrence_service->generate(
            $space_id, $owner_id,
            $skipped_template->id, $period
        );

        $confirmed_id = $confirmed['occurrence']->id;
        $skipped_id = $skipped['occurrence']->id;

        $occurrence_service->resolve(
            $space_id, $owner_id, $confirmed_id, 'confirm'
        );

        $occurrence_service->resolve(
            $space_id, $owner_id, $skipped_id, 'skip'
        );

        foreach (
            [$confirmed_template, $skipped_template] as $template
        ) {
            $recurring_service->setActive(
                $space_id, $owner_id, $template->id, false
            );
        }

        $inactive = $generation_service->generate(
            $space_id, $owner_id, $period
        );

        $this->assertSame(0, $inactive['total']);
        $this->assertSame(2, $this->countOccurrences($space_id));
        $this->assertSame(1, $this->countTransactions($space_id));

        foreach (
            [$confirmed_template, $skipped_template] as $template
        ) {
            $recurring_service->setActive(
                $space_id, $owner_id, $template->id, true
            );
        }

        $reactivated = $generation_service->generate(
            $space_id, $owner_id, $period
        );

        $this->assertSame([
            'created' => 0,
            'existing' => 2,
            'skipped' => 0,
            'total' => 2
        ], $reactivated);

        $confirmed_again = $occurrence_service->generate(
            $space_id, $owner_id,
            $confirmed_template->id, $period
        );

        $skipped_again = $occurrence_service->generate(
            $space_id, $owner_id,
            $skipped_template->id, $period
        );

        $this->assertFalse($confirmed_again['created']);
        $this->assertFalse($skipped_again['created']);

        $this->assertSame(
            $confirmed_id,
            $confirmed_again['occurrence']->id
        );
        $this->assertSame(
            $skipped_id,
            $skipped_again['occurrence']->id
        );

        $this->assertSame(
            'confirmed',
            $confirmed_again['occurrence']->status
        );
        $this->assertSame(
            'skipped',
            $skipped_again['occurrence']->status
        );

        $this->assertSame(2, $this->countOccurrences($space_id));
        $this->assertSame(1, $this->countTransactions($space_id));
    }

    /**
     * Crea el espacio y su categoría de gasto.
     *
     * @return array{int, int, int}
     */
    private function prepareSpace(): array {
        $owner_id = $this->createUser();
        $space = (new SpaceService())->createSharedSpace(
            $owner_id,
            'Espacio generación mensual'
        );

        $this->assertNotNull($space->id);
        $this->spaces[] = $space->id;

        $category = (new CategoryService())->create(
            $space->id, $owner_id, 'Hogar', 'expense'
        );

        $this->assertNotNull($category->id);

        return [$space->id, $owner_id, $category->id];
    }

    /**
     * Crea una plantilla recurrente de gasto.
     *
     * @param int $space_id Espacio.
     * @param int $user_id Propietario.
     * @param int $category_id Categoría.
     * @param string $concept Concepto.
     * @param int $day Día nominal.
     * @param string $start_date Fecha de inicio.
     * @return \Osumi\OsumiFramework\App\Model\RecurringTransaction
     */
    private function createRecurring(
        int $space_id,
        int $user_id,
        int $category_id,
        string $concept,
        int $day,
        string $start_date
    ): \Osumi\OsumiFramework\App\Model\RecurringTransaction {
        return (new RecurringService())->create(
            $space_id, $user_id, $category_id,
            'expense', '50.00', $concept,
            $day, $start_date
        );
    }

    /**
     * Devuelve el primer día del mes de MariaDB.
     *
     * @return string Periodo.
     */
    private function currentPeriod(): string {
        return (string) $this->db->query(
            "SELECT DATE_FORMAT(CURRENT_DATE(), '%Y-%m-01')"
        )->fetchColumn();
    }

    /**
     * Cuenta las ocurrencias de un espacio.
     *
     * @param int $space_id Espacio.
     * @return int Total.
     */
    private function countOccurrences(int $space_id): int {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM `recurring_occurrence`
             WHERE id_space = ?'
        );
        $stmt->execute([$space_id]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Cuenta los movimientos de un espacio.
     *
     * @param int $space_id Espacio.
     * @return int Total.
     */
    private function countTransactions(int $space_id): int {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM `financial_transaction`
             WHERE id_space = ?'
        );
        $stmt->execute([$space_id]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Crea un usuario de integración.
     *
     * @return int Identificador.
     */
    private function createUser(): int {
        $stmt = $this->db->prepare(
            'INSERT INTO `user` (`name`, `email`, `password_hash`)
             VALUES (?, ?, ?)'
        );
        $stmt->execute([
            'Usuario generación',
            'space-occurrence-' .
                bin2hex(random_bytes(12)) . '@example.test',
            password_hash('contraseña segura', PASSWORD_DEFAULT)
        ]);

        $id = (int) $this->db->lastInsertId();
        $this->users[] = $id;

        return $id;
    }
}

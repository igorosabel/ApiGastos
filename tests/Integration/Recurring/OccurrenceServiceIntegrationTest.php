<?php declare(strict_types=1);

require_once dirname(__DIR__) . '/OrmIntegrationTestCase.php';

use Osumi\OsumiFramework\App\Service\Category\CategoryService;
use Osumi\OsumiFramework\App\Service\Recurring\OccurrenceService;
use Osumi\OsumiFramework\App\Service\Recurring\RecurringService;
use Osumi\OsumiFramework\App\Service\Space\SpaceService;

final class OccurrenceServiceIntegrationTest extends OrmIntegrationTestCase {
    /** @var list<int> */
    private array $users = [];

    /** @var list<int> */
    private array $spaces = [];

    /**
     * Limpia los registros introducidos en cada prueba.
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
     * Genera una ocurrencia pendiente con instantánea económica.
     */
    public function testGeneratePendingOccurrence(): void {
        [$space_id, $owner_id, $category_id] = $this->prepareSpace();
        $period = $this->currentPeriod();

        $recurring = $this->recurring(
            $space_id, $owner_id, $category_id, 15
        );

        $result = (new OccurrenceService())->generate(
            $space_id, $owner_id, $recurring->id, $period
        );

        $occurrence = $result['occurrence'];

        $this->assertTrue($result['created']);
        $this->assertNotNull($occurrence->id);
        $this->assertSame($space_id, $occurrence->id_space);
        $this->assertSame($recurring->id, $occurrence->id_recurring_transaction);
        $this->assertSame($period, $occurrence->period);
        $this->assertSame(
            substr($period, 0, 8) . '15',
            $occurrence->scheduled_date
        );
        $this->assertSame('pending', $occurrence->status);
        $this->assertSame($category_id, $occurrence->id_category);
        $this->assertSame('expense', $occurrence->type);
        $this->assertSame('150.00', $occurrence->amount);
        $this->assertSame('Alquiler', $occurrence->concept);
        $this->assertNull($occurrence->id_transaction);
        $this->assertNull($occurrence->resolved_at);
        $this->assertFalse($this->db->inTransaction());

        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM `financial_transaction`
             WHERE id_space = ?'
        );
        $stmt->execute([$space_id]);

        $this->assertSame(0, (int) $stmt->fetchColumn());
    }

    /**
     * Generar dos veces el mismo mes devuelve la misma fila.
     */
    public function testGenerateIsIdempotent(): void {
        [$space_id, $owner_id, $category_id] = $this->prepareSpace();
        $period = $this->currentPeriod();
        $recurring = $this->recurring(
            $space_id, $owner_id, $category_id, 10
        );
        $service = new OccurrenceService();

        $first = $service->generate(
            $space_id, $owner_id, $recurring->id, $period
        );
        $second = $service->generate(
            $space_id, $owner_id, $recurring->id, $period
        );

        $this->assertTrue($first['created']);
        $this->assertFalse($second['created']);
        $this->assertSame(
            $first['occurrence']->id,
            $second['occurrence']->id
        );

        $this->assertSame(
            1,
            $this->countOccurrences($recurring->id)
        );
    }

    /**
     * Una edición posterior no modifica una mensualidad creada.
     */
    public function testExistingSnapshotRemainsUnchanged(): void {
        [$space_id, $owner_id, $category_id] = $this->prepareSpace();
        $period = $this->currentPeriod();
        $recurring_service = new RecurringService();
        $recurring = $this->recurring(
            $space_id, $owner_id, $category_id, 10
        );
        $service = new OccurrenceService();

        $first = $service->generate(
            $space_id, $owner_id, $recurring->id, $period
        );

        $recurring_service->update(
            $space_id, $owner_id, $recurring->id,
            $category_id, 'expense', '200.00',
            'Alquiler actualizado', 20, $period
        );

        $second = $service->generate(
            $space_id, $owner_id, $recurring->id, $period
        );

        $this->assertFalse($second['created']);
        $this->assertSame(
            $first['occurrence']->id,
            $second['occurrence']->id
        );
        $this->assertSame('150.00', $second['occurrence']->amount);
        $this->assertSame('Alquiler', $second['occurrence']->concept);
        $this->assertSame(
            substr($period, 0, 8) . '10',
            $second['occurrence']->scheduled_date
        );
    }

    /**
     * Febrero ajusta el día 31 al 28 o al 29.
     */
    public function testFebruaryUsesLastDay(): void {
        [$space_id, $owner_id, $category_id] = $this->prepareSpace();

        // Elegir un febrero futuro para que nunca resulte
        // anterior al mes real de creación de la plantilla.
        $year = max(
            (int) date('Y') + 1,
            (int) $this->db->query(
                'SELECT YEAR(CURRENT_DATE()) + 1'
            )->fetchColumn()
        );

        $period = sprintf('%04d-02-01', $year);

        $recurring = $this->recurring(
            $space_id, $owner_id, $category_id, 31
        );

        $result = (new OccurrenceService())->generate(
            $space_id, $owner_id, $recurring->id, $period
        );

        $expected = (new DateTimeImmutable($period))
            ->format('Y-m-t');

        $this->assertSame(
            $expected,
            $result['occurrence']->scheduled_date
        );
    }

    /**
     * Una plantilla desactivada no genera meses nuevos.
     */
    public function testInactiveTemplateDoesNotGenerate(): void {
        [$space_id, $owner_id, $category_id] = $this->prepareSpace();
        $period = $this->currentPeriod();
        $service = new RecurringService();
        $recurring = $this->recurring(
            $space_id, $owner_id, $category_id, 10
        );

        $service->setActive(
            $space_id, $owner_id, $recurring->id, false
        );

        $this->expectException(InvalidArgumentException::class);

        (new OccurrenceService())->generate(
            $space_id, $owner_id, $recurring->id, $period
        );
    }

    /**
     * Las mensualidades existentes se siguen devolviendo
     * después de desactivar su plantilla.
     */
    public function testExistingOccurrenceOfInactiveTemplateIsReturned(): void {
        [$space_id, $owner_id, $category_id] = $this->prepareSpace();
        $period = $this->currentPeriod();
        $recurring = $this->recurring(
            $space_id, $owner_id, $category_id, 10
        );
        $service = new OccurrenceService();

        $first = $service->generate(
            $space_id, $owner_id, $recurring->id, $period
        );

        (new RecurringService())->setActive(
            $space_id, $owner_id, $recurring->id, false
        );

        $second = $service->generate(
            $space_id, $owner_id, $recurring->id, $period
        );

        $this->assertFalse($second['created']);
        $this->assertSame(
            $first['occurrence']->id,
            $second['occurrence']->id
        );
    }

    /**
     * El mes anterior al alta de la plantilla queda prohibido.
     */
    public function testCannotGenerateBeforeCreationMonth(): void {
        [$space_id, $owner_id, $category_id] = $this->prepareSpace();
        $recurring = $this->recurring(
            $space_id, $owner_id, $category_id, 10
        );

        $previous = (new DateTimeImmutable($this->currentPeriod()))
            ->modify('-1 month')
            ->format('Y-m-d');

        $this->expectException(InvalidArgumentException::class);

        (new OccurrenceService())->generate(
            $space_id, $owner_id, $recurring->id, $previous
        );
    }

    /**
     * Una fecha de inicio dentro del mes posterior al
     * día programado impide crear esa ocurrencia.
     */
    public function testScheduledDateCannotPrecedeStartDate(): void {
        [$space_id, $owner_id, $category_id] = $this->prepareSpace();
        $period = $this->currentPeriod();

        $recurring = (new RecurringService())->create(
            $space_id, $owner_id, $category_id,
            'expense', '150.00', 'Alquiler',
            10, substr($period, 0, 8) . '20'
        );

        $this->expectException(InvalidArgumentException::class);

        (new OccurrenceService())->generate(
            $space_id, $owner_id, $recurring->id, $period
        );
    }

    /**
     * Una fecha final anterior al día programado
     * impide la generación.
     */
    public function testScheduledDateCannotExceedEndDate(): void {
        [$space_id, $owner_id, $category_id] = $this->prepareSpace();
        $period = $this->currentPeriod();

        $recurring = (new RecurringService())->create(
            $space_id, $owner_id, $category_id,
            'expense', '150.00', 'Alquiler',
            20, $period, substr($period, 0, 8) . '10'
        );

        $this->expectException(InvalidArgumentException::class);

        (new OccurrenceService())->generate(
            $space_id, $owner_id, $recurring->id, $period
        );
    }

    /**
     * Rechaza periodos que no sean el primer día del mes.
     */
    public function testInvalidPeriods(): void {
        [$space_id, $owner_id, $category_id] = $this->prepareSpace();
        $recurring = $this->recurring(
            $space_id, $owner_id, $category_id, 10
        );

        foreach ([
            '2026-10-15',
            '2026-02-30',
            '15/10/2026',
            '2026-13-01'
        ] as $period) {
            try {
                (new OccurrenceService())->generate(
                    $space_id, $owner_id, $recurring->id, $period
                );
                $this->fail("Se ha admitido {$period}.");
            }
            catch (InvalidArgumentException) {
                $this->assertFalse($this->db->inTransaction());
            }
        }
    }

    /**
     * Impide generar ocurrencias de plantillas de otro espacio.
     */
    public function testCannotGenerateFromAnotherSpace(): void {
        [$space_id, $owner_id] = $this->prepareSpace();
        [$other_space_id, $other_owner_id, $other_category_id] =
            $this->prepareSpace();

        $other = $this->recurring(
            $other_space_id, $other_owner_id, $other_category_id, 10
        );

        $this->expectException(InvalidArgumentException::class);

        (new OccurrenceService())->generate(
            $space_id, $owner_id, $other->id,
            $this->currentPeriod()
        );
    }

    /**
     * Un usuario ajeno al espacio no puede generar.
     */
    public function testOutsiderCannotGenerate(): void {
        [$space_id, $owner_id, $category_id] = $this->prepareSpace();
        $outsider_id = $this->createUser();
        $recurring = $this->recurring(
            $space_id, $owner_id, $category_id, 10
        );

        $this->expectException(DomainException::class);

        (new OccurrenceService())->generate(
            $space_id, $outsider_id, $recurring->id,
            $this->currentPeriod()
        );
    }

    /**
     * Un miembro dado de baja no puede generar.
     */
    public function testInactiveMemberCannotGenerate(): void {
        [$space_id, $owner_id, $category_id] = $this->prepareSpace();
        $member_id = $this->createUser();
        $recurring = $this->recurring(
            $space_id, $owner_id, $category_id, 10
        );

        $stmt = $this->db->prepare(
            'INSERT INTO `space_member`
             (id_space, id_user, left_at)
             VALUES (?, ?, NOW())'
        );
        $stmt->execute([$space_id, $member_id]);

        $this->expectException(DomainException::class);

        (new OccurrenceService())->generate(
            $space_id, $member_id, $recurring->id,
            $this->currentPeriod()
        );
    }

    /**
     * Prepara un propietario, espacio y categoría.
     *
     * @return array{int, int, int} IDs.
     */
    private function prepareSpace(): array {
        $owner_id = $this->createUser();
        $space = (new SpaceService())->createSharedSpace(
            $owner_id, 'Espacio de mensualidades'
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
     * Crea una plantilla mensual sin fecha final.
     *
     * @param int $space_id Espacio.
     * @param int $owner_id Propietario.
     * @param int $category_id Categoría.
     * @param int $day Día nominal.
     * @return \Osumi\OsumiFramework\App\Model\RecurringTransaction
     */
    private function recurring(
        int $space_id,
        int $owner_id,
        int $category_id,
        int $day
    ): \Osumi\OsumiFramework\App\Model\RecurringTransaction {
        return (new RecurringService())->create(
            $space_id, $owner_id, $category_id,
            'expense', '150.00', 'Alquiler',
            $day, $this->currentPeriod()
        );
    }

    /**
     * Devuelve el primer día del mes actual de MariaDB.
     *
     * @return string Periodo YYYY-MM-01.
     */
    private function currentPeriod(): string {
        return (string) $this->db->query(
            "SELECT DATE_FORMAT(CURRENT_DATE(), '%Y-%m-01')"
        )->fetchColumn();
    }

    /**
     * Cuenta las ocurrencias de una plantilla.
     *
     * @param int $recurring_id Plantilla.
     * @return int Total.
     */
    private function countOccurrences(int $recurring_id): int {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM `recurring_occurrence`
             WHERE id_recurring_transaction = ?'
        );
        $stmt->execute([$recurring_id]);

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
            'Usuario mensualidad',
            'occurrence-' . bin2hex(random_bytes(12)) .
                '@example.test',
            password_hash('contraseña segura', PASSWORD_DEFAULT)
        ]);

        $id = (int) $this->db->lastInsertId();
        $this->users[] = $id;

        return $id;
    }
}

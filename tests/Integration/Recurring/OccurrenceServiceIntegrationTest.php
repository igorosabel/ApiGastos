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
     * Confirma una mensualidad y registra un único movimiento.
     */
    public function testConfirmCreatesExactlyOneTransaction(): void {
        [$space_id, $owner_id, $category_id] = $this->prepareSpace();
        $period = $this->currentPeriod();
        $recurring = $this->recurring(
            $space_id, $owner_id, $category_id, 15
        );
        $service = new OccurrenceService();

        $generated = $service->generate(
            $space_id, $owner_id, $recurring->id, $period
        );
        $occurrence_id = $generated['occurrence']->id;

        $resolved = $service->resolve(
            $space_id, $owner_id, $occurrence_id, 'confirm'
        );

        $this->assertSame('confirmed', $resolved->status);
        $this->assertNotNull($resolved->id_transaction);
        $this->assertNotNull($resolved->resolved_at);
        $this->assertFalse($this->db->inTransaction());

        $stmt = $this->db->prepare(
            'SELECT id_space, id_user, id_category,
                    type, amount, concept, date
             FROM `financial_transaction`
             WHERE id = ?'
        );
        $stmt->execute([$resolved->id_transaction]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assertIsArray($row);
        $this->assertSame($space_id, (int) $row['id_space']);
        $this->assertSame($owner_id, (int) $row['id_user']);
        $this->assertSame($category_id, (int) $row['id_category']);
        $this->assertSame('expense', $row['type']);
        $this->assertSame('150.00', $row['amount']);
        $this->assertSame('Alquiler', $row['concept']);
        $this->assertSame(
            substr($period, 0, 8) . '15',
            $row['date']
        );

        $this->assertSame(1, $this->countSpaceTransactions($space_id));
    }

    /**
     * Omite una mensualidad sin registrar movimiento.
     */
    public function testSkipDoesNotCreateTransaction(): void {
        [$space_id, $owner_id, $category_id] = $this->prepareSpace();
        $period = $this->currentPeriod();
        $recurring = $this->recurring(
            $space_id, $owner_id, $category_id, 10
        );
        $service = new OccurrenceService();

        $generated = $service->generate(
            $space_id, $owner_id, $recurring->id, $period
        );

        $resolved = $service->resolve(
            $space_id,
            $owner_id,
            $generated['occurrence']->id,
            'skip'
        );

        $this->assertSame('skipped', $resolved->status);
        $this->assertNull($resolved->id_transaction);
        $this->assertNotNull($resolved->resolved_at);
        $this->assertSame(0, $this->countSpaceTransactions($space_id));
    }

    /**
     * Una ocurrencia confirmada no puede resolverse otra vez.
     */
    public function testConfirmCannotBeRepeated(): void {
        [$space_id, $owner_id, $category_id] = $this->prepareSpace();
        $recurring = $this->recurring(
            $space_id, $owner_id, $category_id, 10
        );
        $service = new OccurrenceService();

        $generated = $service->generate(
            $space_id, $owner_id, $recurring->id,
            $this->currentPeriod()
        );
        $occurrence_id = $generated['occurrence']->id;

        $service->resolve(
            $space_id, $owner_id, $occurrence_id, 'confirm'
        );

        try {
            $service->resolve(
                $space_id, $owner_id, $occurrence_id, 'confirm'
            );
            $this->fail('Se ha confirmado dos veces.');
        }
        catch (LogicException) {
            $this->assertSame(
                1,
                $this->countSpaceTransactions($space_id)
            );
            $this->assertFalse($this->db->inTransaction());
        }
    }

    /**
     * Una ocurrencia omitida no puede confirmarse posteriormente.
     */
    public function testSkippedOccurrenceCannotBeConfirmed(): void {
        [$space_id, $owner_id, $category_id] = $this->prepareSpace();
        $recurring = $this->recurring(
            $space_id, $owner_id, $category_id, 10
        );
        $service = new OccurrenceService();

        $generated = $service->generate(
            $space_id, $owner_id, $recurring->id,
            $this->currentPeriod()
        );
        $occurrence_id = $generated['occurrence']->id;

        $service->resolve(
            $space_id, $owner_id, $occurrence_id, 'skip'
        );

        try {
            $service->resolve(
                $space_id, $owner_id, $occurrence_id, 'confirm'
            );
            $this->fail('Se ha confirmado una ocurrencia omitida.');
        }
        catch (LogicException) {
            $this->assertSame(
                0,
                $this->countSpaceTransactions($space_id)
            );
        }
    }

    /**
     * La confirmación utiliza la instantánea, no la plantilla editada.
     */
    public function testConfirmUsesOccurrenceSnapshot(): void {
        [$space_id, $owner_id, $category_id] = $this->prepareSpace();
        $period = $this->currentPeriod();
        $recurring = $this->recurring(
            $space_id, $owner_id, $category_id, 10
        );
        $service = new OccurrenceService();

        $generated = $service->generate(
            $space_id, $owner_id, $recurring->id, $period
        );

        (new RecurringService())->update(
            $space_id, $owner_id, $recurring->id,
            $category_id, 'expense', '250.00',
            'Alquiler nuevo', 20, $period
        );

        $resolved = $service->resolve(
            $space_id,
            $owner_id,
            $generated['occurrence']->id,
            'confirm'
        );

        $stmt = $this->db->prepare(
            'SELECT amount, concept, date
             FROM `financial_transaction`
             WHERE id = ?'
        );
        $stmt->execute([$resolved->id_transaction]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assertIsArray($row);
        $this->assertSame('150.00', $row['amount']);
        $this->assertSame('Alquiler', $row['concept']);
        $this->assertSame(
            substr($period, 0, 8) . '10',
            $row['date']
        );
    }

    /**
     * Desactivar la plantilla no impide confirmar una pendiente.
     */
    public function testInactiveTemplateCanHavePendingResolved(): void {
        [$space_id, $owner_id, $category_id] = $this->prepareSpace();
        $recurring = $this->recurring(
            $space_id, $owner_id, $category_id, 10
        );
        $service = new OccurrenceService();

        $generated = $service->generate(
            $space_id, $owner_id, $recurring->id,
            $this->currentPeriod()
        );

        (new RecurringService())->setActive(
            $space_id, $owner_id, $recurring->id, false
        );

        $resolved = $service->resolve(
            $space_id, $owner_id,
            $generated['occurrence']->id, 'confirm'
        );

        $this->assertSame('confirmed', $resolved->status);
        $this->assertSame(1, $this->countSpaceTransactions($space_id));
    }

    /**
     * El listado filtra los estados e incluye los ya resueltos.
     */
    public function testGetBySpaceFiltersStatus(): void {
        [$space_id, $owner_id, $category_id] = $this->prepareSpace();
        $recurring = $this->recurring(
            $space_id, $owner_id, $category_id, 10
        );
        $service = new OccurrenceService();

        $first_period = $this->currentPeriod();
        $next_period = (new DateTimeImmutable($first_period))
            ->modify('+1 month')
            ->format('Y-m-d');

        $first = $service->generate(
            $space_id, $owner_id, $recurring->id,
            $first_period
        );
        $service->generate(
            $space_id, $owner_id, $recurring->id,
            $next_period
        );

        $service->resolve(
            $space_id, $owner_id,
            $first['occurrence']->id, 'skip'
        );

        $this->assertCount(
            2,
            $service->getBySpace($space_id, $owner_id)
        );
        $this->assertCount(
            1,
            $service->getBySpace($space_id, $owner_id, 'pending')
        );
        $this->assertCount(
            1,
            $service->getBySpace($space_id, $owner_id, 'skipped')
        );
        $this->assertSame(
            [],
            $service->getBySpace($space_id, $owner_id, 'confirmed')
        );
    }

    /**
     * Un usuario ajeno no puede consultar ocurrencias.
     */
    public function testOutsiderCannotListOccurrences(): void {
        [$space_id] = $this->prepareSpace();
        $outsider = $this->createUser();

        $this->expectException(DomainException::class);

        (new OccurrenceService())->getBySpace(
            $space_id, $outsider
        );
    }

    /**
     * Un usuario ajeno no puede resolver ocurrencias.
     */
    public function testOutsiderCannotResolveOccurrence(): void {
        [$space_id, $owner_id, $category_id] = $this->prepareSpace();
        $outsider = $this->createUser();
        $recurring = $this->recurring(
            $space_id, $owner_id, $category_id, 10
        );

        $service = new OccurrenceService();
        $generated = $service->generate(
            $space_id, $owner_id, $recurring->id,
            $this->currentPeriod()
        );

        $this->expectException(DomainException::class);

        $service->resolve(
            $space_id, $outsider,
            $generated['occurrence']->id, 'confirm'
        );
    }

    /**
     * No permite resolver una ocurrencia de otro espacio.
     */
    public function testCannotResolveOtherSpacesOccurrence(): void {
        [$space_id, $owner_id] = $this->prepareSpace();
        [$other_space_id, $other_owner_id, $category_id] =
            $this->prepareSpace();

        $recurring = $this->recurring(
            $other_space_id, $other_owner_id,
            $category_id, 10
        );
        $service = new OccurrenceService();
        $generated = $service->generate(
            $other_space_id, $other_owner_id,
            $recurring->id, $this->currentPeriod()
        );

        $this->expectException(InvalidArgumentException::class);

        $service->resolve(
            $space_id, $owner_id,
            $generated['occurrence']->id, 'confirm'
        );
    }

    /**
     * Rechaza acciones de resolución inválidas.
     */
    public function testResolveRejectsInvalidAction(): void {
        $this->expectException(InvalidArgumentException::class);

        (new OccurrenceService())->resolve(1, 1, 1, 'delete');
    }

    /**
     * Rechaza filtros de estado inválidos.
     */
    public function testGetBySpaceRejectsInvalidStatus(): void {
        $this->expectException(InvalidArgumentException::class);

        (new OccurrenceService())->getBySpace(1, 1, 'unknown');
    }

    /**
     * Comprueba los valores por defecto de búsqueda.
     */
    public function testSearchDefaults(): void {
        [$space_id, $owner_id, $category_id] =
            $this->prepareSpace();

        $template = $this->recurring(
            $space_id, $owner_id, $category_id, 15
        );

        $service = new OccurrenceService();
        $generated = $service->generate(
            $space_id,
            $owner_id,
            $template->id,
            $this->currentPeriod()
        );

        $result = $service->search($space_id, $owner_id);

        $this->assertSame(1, $result['total']);
        $this->assertSame(1, $result['page']);
        $this->assertSame(20, $result['page_size']);
        $this->assertSame(1, $result['total_pages']);
        $this->assertCount(1, $result['occurrences']);
        $this->assertSame(
            $generated['occurrence']->id,
            $result['occurrences'][0]->id
        );
    }

    /**
     * Comprueba páginas y orden estable cuando varias
     * ocurrencias comparten fecha programada.
     */
    public function testSearchPaginationAndOrdering(): void {
        [$space_id, $owner_id, $category_id] =
            $this->prepareSpace();

        $service = new OccurrenceService();
        $period = $this->currentPeriod();
        $ids = [];

        for ($i = 0; $i < 5; $i++) {
            $template = $this->recurring(
                $space_id,
                $owner_id,
                $category_id,
                15
            );

            $generated = $service->generate(
                $space_id,
                $owner_id,
                $template->id,
                $period
            );

            $ids[] = $generated['occurrence']->id;
        }

        $first = $service->search(
            $space_id, $owner_id,
            page: 1, page_size: 2
        );

        $second = $service->search(
            $space_id, $owner_id,
            page: 2, page_size: 2
        );

        $third = $service->search(
            $space_id, $owner_id,
            page: 3, page_size: 2
        );

        $this->assertSame(5, $first['total']);
        $this->assertSame(3, $first['total_pages']);

        $extract_ids = static fn(array $list): array =>
            array_map(
                static fn($item): ?int => $item->id,
                $list
            );

        $this->assertSame(
            [$ids[4], $ids[3]],
            $extract_ids($first['occurrences'])
        );
        $this->assertSame(
            [$ids[2], $ids[1]],
            $extract_ids($second['occurrences'])
        );
        $this->assertSame(
            [$ids[0]],
            $extract_ids($third['occurrences'])
        );
    }

    /**
     * Comprueba filtros combinados de periodo, estado
     * y plantilla recurrente.
     */
    public function testSearchCombinedFilters(): void {
        [$space_id, $owner_id, $category_id] =
            $this->prepareSpace();

        $period = $this->currentPeriod();
        $next = (new DateTimeImmutable($period))
            ->modify('+1 month')
            ->format('Y-m-d');

        $first_template = $this->recurring(
            $space_id, $owner_id, $category_id, 10
        );
        $second_template = $this->recurring(
            $space_id, $owner_id, $category_id, 20
        );

        $service = new OccurrenceService();

        $expected = $service->generate(
            $space_id, $owner_id, $first_template->id, $period
        );

        $service->generate(
            $space_id, $owner_id, $first_template->id, $next
        );

        $other = $service->generate(
            $space_id, $owner_id, $second_template->id, $period
        );

        $service->resolve(
            $space_id, $owner_id,
            $other['occurrence']->id, 'skip'
        );

        $result = $service->search(
            $space_id,
            $owner_id,
            status: 'pending',
            period_from: $period,
            period_to: $period,
            recurring_id: $first_template->id
        );

        $this->assertSame(1, $result['total']);
        $this->assertSame(
            $expected['occurrence']->id,
            $result['occurrences'][0]->id
        );
        $this->assertSame(
            'pending',
            $result['occurrences'][0]->status
        );
    }

    /**
     * Una página fuera de rango devuelve lista vacía.
     */
    public function testSearchOutsideRange(): void {
        [$space_id, $owner_id, $category_id] =
            $this->prepareSpace();

        $template = $this->recurring(
            $space_id, $owner_id, $category_id, 15
        );

        $service = new OccurrenceService();
        $service->generate(
            $space_id, $owner_id,
            $template->id, $this->currentPeriod()
        );

        $result = $service->search(
            $space_id, $owner_id,
            page: 5, page_size: 1
        );

        $this->assertSame(1, $result['total']);
        $this->assertSame(1, $result['total_pages']);
        $this->assertSame([], $result['occurrences']);
    }

    /**
     * Una consulta sin coincidencias conserva metadatos.
     */
    public function testSearchEmptyResult(): void {
        [$space_id, $owner_id] = $this->prepareSpace();

        $result = (new OccurrenceService())->search(
            $space_id, $owner_id,
            status: 'confirmed'
        );

        $this->assertSame(0, $result['total']);
        $this->assertSame(0, $result['total_pages']);
        $this->assertSame(1, $result['page']);
        $this->assertSame([], $result['occurrences']);
    }

    /**
     * Rechaza formatos de fechas y parámetros inválidos.
     */
    public function testSearchInvalidFilters(): void {
        [$space_id, $owner_id] = $this->prepareSpace();

        $service = new OccurrenceService();

        $cases = [
            ['status' => 'deleted'],
            ['period_from' => '2026-10-15'],
            ['period_from' => '2026-02-30'],
            ['period_to' => '10/2026'],
            [
                'period_from' => '2026-12-01',
                'period_to' => '2026-11-01'
            ],
            ['recurring_id' => 0],
            ['page' => 0],
            ['page' => 100001],
            ['page_size' => 0],
            ['page_size' => 101]
        ];

        foreach ($cases as $filters) {
            try {
                $service->search(
                    $space_id,
                    $owner_id,
                    ...$filters
                );
                $this->fail(
                    'Se ha aceptado un filtro inválido.'
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
     * Un usuario ajeno no puede acceder a la búsqueda.
     */
    public function testOutsiderCannotSearch(): void {
        [$space_id] = $this->prepareSpace();
        $outsider_id = $this->createUser();

        $this->expectException(DomainException::class);

        (new OccurrenceService())->search(
            $space_id, $outsider_id
        );
    }

    /**
     * Una membresía inactiva no permite la búsqueda.
     */
    public function testInactiveMemberCannotSearch(): void {
        [$space_id] = $this->prepareSpace();
        $member_id = $this->createUser();

        $stmt = $this->db->prepare(
            'INSERT INTO `space_member`
             (id_space, id_user, left_at)
             VALUES (?, ?, NOW())'
        );

        $stmt->execute([$space_id, $member_id]);

        $this->expectException(DomainException::class);

        (new OccurrenceService())->search(
            $space_id, $member_id
        );
    }

    /**
     * No se mezclan ocurrencias entre espacios.
     */
    public function testSearchIsolatedBySpace(): void {
        [$space_id, $owner_id, $category_id] =
            $this->prepareSpace();

        [$other_space_id, $other_owner_id, $other_category_id] =
            $this->prepareSpace();

        $period = $this->currentPeriod();
        $service = new OccurrenceService();

        $first = $this->recurring(
            $space_id, $owner_id, $category_id, 10
        );

        $second = $this->recurring(
            $other_space_id, $other_owner_id,
            $other_category_id, 10
        );

        $service->generate(
            $space_id, $owner_id, $first->id, $period
        );

        $service->generate(
            $other_space_id, $other_owner_id,
            $second->id, $period
        );

        $result = $service->search($space_id, $owner_id);

        $this->assertSame(1, $result['total']);
        $this->assertSame(
            $space_id,
            $result['occurrences'][0]->id_space
        );
    }

    /**
     * Comprueba que una generación concurrente respeta
     * el bloqueo del espacio y puede reintentarse.
     */
    public function testGenerationWaitsForSpaceLock(): void {
        [$space_id, $owner_id, $category_id] =
            $this->prepareSpace();

        $period = $this->currentPeriod();

        $recurring = $this->recurring(
            $space_id, $owner_id, $category_id, 10
        );

        $second = $this->createSecondConnection();
        $original_core = $GLOBALS['core'];

        try {
            // La primera conexión mantiene el bloqueo
            // utilizado por las operaciones económicas.
            $this->assertTrue($this->db->beginTransaction());

            $stmt = $this->db->prepare(
                'SELECT id FROM `space`
                 WHERE id = ? FOR UPDATE'
            );
            $stmt->execute([$space_id]);

            $this->assertSame(
                $space_id,
                (int) $stmt->fetchColumn()
            );

            // Ejecutar el servicio real mediante una
            // segunda conexión física a MariaDB.
            $this->setOrmConnection($second);

            try {
                (new OccurrenceService())->generate(
                    $space_id,
                    $owner_id,
                    $recurring->id,
                    $period
                );

                $this->fail(
                    'La generación ha ignorado el bloqueo.'
                );
            }
            catch (PDOException $e) {
                $this->assertSame(
                    1205,
                    (int) ($e->errorInfo[1] ?? 0),
                    'Se esperaba un timeout de bloqueo InnoDB.'
                );
            }

            // El servicio debe haber revertido su transacción.
            $this->assertFalse($second->inTransaction());
        }
        finally {
            // Restaurar siempre la conexión principal
            // antes de continuar con la integración.
            $GLOBALS['core'] = $original_core;

            if ($second->inTransaction()) {
                $second->rollBack();
            }

            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
        }

        // El intento bloqueado no debe haber creado nada.
        $this->assertSame(
            0,
            $this->countOccurrences($recurring->id)
        );

        // Sin el bloqueo, la generación funciona.
        $service = new OccurrenceService();

        $first = $service->generate(
            $space_id, $owner_id, $recurring->id, $period
        );

        $this->assertTrue($first['created']);

        // Un nuevo intento conserva la idempotencia.
        $second_attempt = $service->generate(
            $space_id, $owner_id, $recurring->id, $period
        );

        $this->assertFalse($second_attempt['created']);
        $this->assertSame(
            $first['occurrence']->id,
            $second_attempt['occurrence']->id
        );

        $this->assertSame(
            1,
            $this->countOccurrences($recurring->id)
        );
        $this->assertSame(
            0,
            $this->countSpaceTransactions($space_id)
        );
        $this->assertFalse($this->db->inTransaction());
    }

    /**
     * Comprueba que una confirmación no puede avanzar
     * si otra conexión tiene bloqueada la ocurrencia.
     */
    public function testConfirmationWaitsForOccurrenceLock(): void {
        [$space_id, $owner_id, $category_id] =
            $this->prepareSpace();

        $recurring = $this->recurring(
            $space_id, $owner_id, $category_id, 10
        );

        $service = new OccurrenceService();

        $generated = $service->generate(
            $space_id,
            $owner_id,
            $recurring->id,
            $this->currentPeriod()
        );

        $occurrence_id = $generated['occurrence']->id;

        $this->assertNotNull($occurrence_id);

        $second = $this->createSecondConnection();
        $original_core = $GLOBALS['core'];

        try {
            $this->assertTrue($this->db->beginTransaction());

            // En este caso bloqueamos directamente la
            // ocurrencia, no el espacio.
            $stmt = $this->db->prepare(
                'SELECT id FROM `recurring_occurrence`
                 WHERE id = ? FOR UPDATE'
            );
            $stmt->execute([$occurrence_id]);

            $this->assertSame(
                $occurrence_id,
                (int) $stmt->fetchColumn()
            );

            $this->setOrmConnection($second);

            try {
                (new OccurrenceService())->resolve(
                    $space_id,
                    $owner_id,
                    $occurrence_id,
                    'confirm'
                );

                $this->fail(
                    'La confirmación ha ignorado el bloqueo.'
                );
            }
            catch (PDOException $e) {
                $this->assertSame(
                    1205,
                    (int) ($e->errorInfo[1] ?? 0),
                    'Se esperaba un timeout de bloqueo InnoDB.'
                );
            }

            $this->assertFalse($second->inTransaction());
        }
        finally {
            $GLOBALS['core'] = $original_core;

            if ($second->inTransaction()) {
                $second->rollBack();
            }

            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
        }

        // El intento fallido no ha confirmado nada.
        $stmt = $this->db->prepare(
            'SELECT status, id_transaction
             FROM `recurring_occurrence`
             WHERE id = ?'
        );
        $stmt->execute([$occurrence_id]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assertIsArray($row);
        $this->assertSame('pending', $row['status']);
        $this->assertNull($row['id_transaction']);

        $this->assertSame(
            0,
            $this->countSpaceTransactions($space_id)
        );

        // Liberado el bloqueo, confirmar debe funcionar.
        $resolved = (new OccurrenceService())->resolve(
            $space_id,
            $owner_id,
            $occurrence_id,
            'confirm'
        );

        $this->assertSame('confirmed', $resolved->status);
        $this->assertNotNull($resolved->id_transaction);

        $this->assertSame(
            1,
            $this->countSpaceTransactions($space_id)
        );
        $this->assertFalse($this->db->inTransaction());
    }

    /**
     * Cuenta los movimientos reales registrados en un espacio.
     *
     * @param int $space_id Espacio.
     * @return int Total de movimientos.
     */
    private function countSpaceTransactions(int $space_id): int {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM `financial_transaction`
             WHERE id_space = ?'
        );
        $stmt->execute([$space_id]);

        return (int) $stmt->fetchColumn();
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

    /**
     * Abre una segunda conexión independiente a gastos_test.
     *
     * Limita el tiempo de espera de bloqueos exclusivamente
     * en esta sesión para permitir pruebas de contención.
     *
     * @return PDO Segunda conexión de integración.
     */
    private function createSecondConnection(): PDO {
        $dsn = getenv('GASTOS_TEST_DSN');
        $user = getenv('GASTOS_TEST_USER');
        $password = getenv('GASTOS_TEST_PASSWORD');

        if (
            !is_string($dsn) ||
            !is_string($user) ||
            !is_string($password)
        ) {
            throw new RuntimeException(
                'Falta configurar la conexión de integración.'
            );
        }

        $connection = new PDO(
            $dsn,
            $user,
            $password,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_EMULATE_PREPARES => false
            ]
        );

        // Comprobación obligatoria antes de utilizar
        // esta conexión para cualquier operación.
        $database = $connection->query(
            'SELECT DATABASE()'
        )->fetchColumn();

        if ($database !== 'gastos_test') {
            throw new RuntimeException(
                'Las pruebas de concurrencia solo pueden usar gastos_test.'
            );
        }

        // Asegurar que se trata de dos sesiones distintas.
        $main_connection_id = (int) $this->db
            ->query('SELECT CONNECTION_ID()')
            ->fetchColumn();

        $second_connection_id = (int) $connection
            ->query('SELECT CONNECTION_ID()')
            ->fetchColumn();

        $this->assertNotSame(
            $main_connection_id,
            $second_connection_id,
            'Las conexiones deben ser sesiones independientes.'
        );

        // Aplicar el timeout únicamente a la segunda sesión.
        $connection->exec(
            'SET SESSION innodb_lock_wait_timeout = 1'
        );

        return $connection;
    }
}

<?php declare(strict_types=1);

require_once dirname(__DIR__) . '/OrmIntegrationTestCase.php';

use Osumi\OsumiFramework\App\Service\Category\CategoryService;
use Osumi\OsumiFramework\App\Service\Recurring\RecurringService;
use Osumi\OsumiFramework\App\Service\Space\SpaceService;

final class RecurringServiceIntegrationTest extends OrmIntegrationTestCase {
    /** @var list<int> */
    private array $users = [];

    /** @var list<int> */
    private array $spaces = [];

    /**
     * Elimina exclusivamente los registros de prueba.
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
     * Comprueba el almacenamiento decimal y la plantilla activa.
     */
    public function testCreateRecurringExpense(): void {
        [$space_id, $owner_id, $category_id] =
            $this->prepareSpace();

        $recurring = (new RecurringService())->create(
            $space_id, $owner_id, $category_id,
            'expense', '850.75', '  Alquiler  ',
            31, '2026-11-01'
        );

        $this->assertNotNull($recurring->id);
        $this->assertSame('850.75', $recurring->amount);
        $this->assertSame('Alquiler', $recurring->concept);
        $this->assertSame(31, $recurring->day_of_month);
        $this->assertNull($recurring->end_date);
        $this->assertTrue($recurring->active);

        $stmt = $this->db->prepare(
            'SELECT amount, active, end_date
             FROM `recurring_transaction` WHERE id = ?'
        );
        $stmt->execute([$recurring->id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assertIsArray($row);
        $this->assertSame('850.75', $row['amount']);
        $this->assertSame(1, (int) $row['active']);
        $this->assertNull($row['end_date']);
        $this->assertFalse($this->db->inTransaction());
    }

    /**
     * Comprueba una plantilla con fecha final.
     */
    public function testCreateWithEndDate(): void {
        [$space_id, $owner_id, $category_id] =
            $this->prepareSpace();

        $recurring = (new RecurringService())->create(
            $space_id, $owner_id, $category_id,
            'expense', '40.00', 'Suscripción',
            15, '2026-10-01', '2026-12-31'
        );

        $this->assertSame('2026-12-31', $recurring->end_date);
    }

    /**
     * Comprueba el listado por espacio.
     */
    public function testGetBySpace(): void {
        [$space_id, $owner_id, $category_id] =
            $this->prepareSpace();
        $service = new RecurringService();

        $service->create(
            $space_id, $owner_id, $category_id,
            'expense', '10.00', 'Primera',
            1, '2026-10-01'
        );
        $service->create(
            $space_id, $owner_id, $category_id,
            'expense', '20.00', 'Segunda',
            15, '2026-10-01'
        );

        $list = $service->getBySpace($space_id, $owner_id);

        $this->assertCount(2, $list);
        $this->assertSame('Primera', $list[0]->concept);
        $this->assertSame('Segunda', $list[1]->concept);
    }

    /**
     * Comprueba las validaciones de importes, días y fechas.
     */
    public function testInvalidInputs(): void {
        [$space_id, $owner_id, $category_id] =
            $this->prepareSpace();
        $service = new RecurringService();

        $invalid = [
            ['expense', '0.00', 'Prueba', 15, '2026-10-01', null],
            ['expense', '10.123', 'Prueba', 15, '2026-10-01', null],
            ['expense', '1e3', 'Prueba', 15, '2026-10-01', null],
            ['other', '10.00', 'Prueba', 15, '2026-10-01', null],
            ['expense', '10.00', '  ', 15, '2026-10-01', null],
            ['expense', '10.00', 'Prueba', 0, '2026-10-01', null],
            ['expense', '10.00', 'Prueba', 32, '2026-10-01', null],
            ['expense', '10.00', 'Prueba', 15, '2026-02-30', null],
            ['expense', '10.00', 'Prueba', 15, '2026-11-01', '2026-10-01']
        ];

        foreach ($invalid as $args) {
            try {
                $service->create(
                    $space_id,
                    $owner_id,
                    $category_id,
                    ...$args
                );
                $this->fail('Se ha admitido una plantilla inválida.');
            }
            catch (InvalidArgumentException) {
                $this->assertFalse($this->db->inTransaction());
            }
        }
    }

    /**
     * Comprueba que la categoría ha de pertenecer al espacio.
     */
    public function testCategoryFromAnotherSpace(): void {
        [$space_id, $owner_id] = $this->prepareSpace();
        [, , $other_category_id] = $this->prepareSpace();

        $this->expectException(InvalidArgumentException::class);

        (new RecurringService())->create(
            $space_id, $owner_id, $other_category_id,
            'expense', '10.00', 'Prueba',
            15, '2026-10-01'
        );
    }

    /**
     * Comprueba que la categoría coincide con el tipo.
     */
    public function testCategoryTypeMismatch(): void {
        [$space_id, $owner_id] = $this->prepareSpace();

        $income_category = (new CategoryService())->create(
            $space_id, $owner_id, 'Salario', 'income'
        );

        $this->expectException(InvalidArgumentException::class);

        (new RecurringService())->create(
            $space_id, $owner_id, $income_category->id,
            'expense', '10.00', 'Prueba',
            15, '2026-10-01'
        );
    }

    /**
     * Comprueba que un usuario ajeno no consulta ni crea.
     */
    public function testOutsiderCannotCreate(): void {
        [$space_id, , $category_id] = $this->prepareSpace();
        $outsider_id = $this->createUser();

        $this->expectException(DomainException::class);

        (new RecurringService())->create(
            $space_id, $outsider_id, $category_id,
            'expense', '10.00', 'Prueba',
            15, '2026-10-01'
        );
    }

    /**
     * Comprueba que un usuario ajeno no puede listar.
     */
    public function testOutsiderCannotList(): void {
        [$space_id] = $this->prepareSpace();
        $outsider_id = $this->createUser();

        $this->expectException(DomainException::class);

        (new RecurringService())->getBySpace(
            $space_id,
            $outsider_id
        );
    }

    /**
     * Comprueba que una membresía inactiva no concede acceso.
     */
    public function testInactiveMemberCannotList(): void {
        [$space_id] = $this->prepareSpace();
        $guest_id = $this->createUser();

        $stmt = $this->db->prepare(
            'INSERT INTO `space_member`
             (id_space, id_user, left_at)
             VALUES (?, ?, NOW())'
        );
        $stmt->execute([$space_id, $guest_id]);

        $this->expectException(DomainException::class);

        (new RecurringService())->getBySpace(
            $space_id,
            $guest_id
        );
    }

    /**
     * Comprueba la modificación de los datos de la plantilla.
     */
    public function testUpdateRecurringTemplate(): void {
        [$space_id, $owner_id, $expense_id] = $this->prepareSpace();

        $service = new RecurringService();
        $income = (new CategoryService())->create(
            $space_id, $owner_id, 'Sueldo', 'income'
        );

        $original = $service->create(
            $space_id, $owner_id, $expense_id,
            'expense', '850.00', 'Alquiler',
            31, '2026-10-01'
        );

        $updated = $service->update(
            $space_id, $owner_id, $original->id,
            $income->id, 'income', '2250.75',
            '  Nómina  ', 5, '2026-11-01', '2027-12-31'
        );

        $this->assertSame($original->id, $updated->id);
        $this->assertSame($space_id, $updated->id_space);
        $this->assertSame($income->id, $updated->id_category);
        $this->assertSame('income', $updated->type);
        $this->assertSame('2250.75', $updated->amount);
        $this->assertSame('Nómina', $updated->concept);
        $this->assertSame(5, $updated->day_of_month);
        $this->assertSame('2026-11-01', $updated->start_date);
        $this->assertSame('2027-12-31', $updated->end_date);
        $this->assertTrue($updated->active);
        $this->assertFalse($this->db->inTransaction());
    }

    /**
     * Comprueba que se puede eliminar la fecha final.
     */
    public function testUpdateCanClearEndDate(): void {
        [$space_id, $owner_id, $category_id] = $this->prepareSpace();
        $service = new RecurringService();

        $recurring = $service->create(
            $space_id, $owner_id, $category_id,
            'expense', '50.00', 'Servicio',
            15, '2026-10-01', '2026-12-31'
        );

        $updated = $service->update(
            $space_id, $owner_id, $recurring->id,
            $category_id, 'expense', '50.00',
            'Servicio', 15, '2026-10-01', null
        );

        $this->assertNull($updated->end_date);
    }

    /**
     * Comprueba la desactivación y reactivación.
     */
    public function testDeactivateAndReactivate(): void {
        [$space_id, $owner_id, $category_id] = $this->prepareSpace();
        $service = new RecurringService();

        $recurring = $service->create(
            $space_id, $owner_id, $category_id,
            'expense', '50.00', 'Servicio',
            15, '2026-10-01'
        );

        $inactive = $service->setActive(
            $space_id, $owner_id, $recurring->id, false
        );

        $this->assertFalse($inactive->active);
        $this->assertFalse($this->db->inTransaction());

        $list = $service->getBySpace($space_id, $owner_id);
        $this->assertCount(1, $list);
        $this->assertFalse($list[0]->active);

        $active = $service->setActive(
            $space_id, $owner_id, $recurring->id, true
        );

        $this->assertTrue($active->active);
    }

    /**
     * Comprueba que las ocurrencias existentes conservan
     * los valores originales después de editar la plantilla.
     */
    public function testExistingOccurrenceSnapshotIsPreserved(): void {
        [$space_id, $owner_id, $category_id] = $this->prepareSpace();
        $service = new RecurringService();

        $recurring = $service->create(
            $space_id, $owner_id, $category_id,
            'expense', '100.00', 'Cuota original',
            10, '2026-10-01'
        );

        $stmt = $this->db->prepare(
            'INSERT INTO `recurring_occurrence`
             (id_space, id_recurring_transaction, period,
              scheduled_date, status, id_category,
              type, concept, amount)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $space_id, $recurring->id, '2026-10-01',
            '2026-10-10', 'pending', $category_id,
            'expense', 'Cuota original', '100.00'
        ]);

        $occurrence_id = (int) $this->db->lastInsertId();

        $service->update(
            $space_id, $owner_id, $recurring->id,
            $category_id, 'expense', '125.00',
            'Cuota nueva', 20, '2026-10-01'
        );

        $service->setActive(
            $space_id, $owner_id, $recurring->id, false
        );

        $stmt = $this->db->prepare(
            'SELECT scheduled_date, status, id_category,
                    type, concept, amount
             FROM `recurring_occurrence`
             WHERE id = ?'
        );
        $stmt->execute([$occurrence_id]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assertIsArray($row);
        $this->assertSame('2026-10-10', $row['scheduled_date']);
        $this->assertSame('pending', $row['status']);
        $this->assertSame($category_id, (int) $row['id_category']);
        $this->assertSame('expense', $row['type']);
        $this->assertSame('Cuota original', $row['concept']);
        $this->assertSame('100.00', $row['amount']);
    }

    /**
     * Comprueba las validaciones de edición.
     */
    public function testUpdateRejectsInvalidData(): void {
        [$space_id, $owner_id, $category_id] = $this->prepareSpace();
        $service = new RecurringService();

        $recurring = $service->create(
            $space_id, $owner_id, $category_id,
            'expense', '50.00', 'Servicio',
            15, '2026-10-01'
        );

        $cases = [
            ['expense', '0.00', 'Servicio', 15, '2026-10-01', null],
            ['expense', '1.234', 'Servicio', 15, '2026-10-01', null],
            ['other', '10.00', 'Servicio', 15, '2026-10-01', null],
            ['expense', '10.00', ' ', 15, '2026-10-01', null],
            ['expense', '10.00', 'Servicio', 0, '2026-10-01', null],
            ['expense', '10.00', 'Servicio', 32, '2026-10-01', null],
            ['expense', '10.00', 'Servicio', 15, '2026-02-30', null],
            ['expense', '10.00', 'Servicio', 15, '2026-11-01', '2026-10-01']
        ];

        foreach ($cases as $args) {
            try {
                $service->update(
                    $space_id, $owner_id, $recurring->id,
                    $category_id, ...$args
                );
                $this->fail('Se ha aceptado una edición inválida.');
            }
            catch (InvalidArgumentException) {
                $this->assertFalse($this->db->inTransaction());
            }
        }

        $list = $service->getBySpace($space_id, $owner_id);
        $this->assertSame('50.00', $list[0]->amount);
    }

    /**
     * Comprueba que se rechaza una categoría incompatible.
     */
    public function testUpdateRejectsCategoryTypeMismatch(): void {
        [$space_id, $owner_id, $category_id] = $this->prepareSpace();
        $service = new RecurringService();

        $income = (new CategoryService())->create(
            $space_id, $owner_id, 'Ingreso', 'income'
        );

        $recurring = $service->create(
            $space_id, $owner_id, $category_id,
            'expense', '10.00', 'Cuota',
            10, '2026-10-01'
        );

        $this->expectException(InvalidArgumentException::class);

        $service->update(
            $space_id, $owner_id, $recurring->id,
            $income->id, 'expense', '10.00',
            'Cuota', 10, '2026-10-01'
        );
    }

    /**
     * Comprueba que otro miembro activo puede editar.
     */
    public function testActiveMemberCanUpdate(): void {
        [$space_id, $owner_id, $category_id] = $this->prepareSpace();
        $member_id = $this->createUser();
        $service = new RecurringService();

        $stmt = $this->db->prepare(
            'INSERT INTO `space_member` (id_space, id_user)
             VALUES (?, ?)'
        );
        $stmt->execute([$space_id, $member_id]);

        $recurring = $service->create(
            $space_id, $owner_id, $category_id,
            'expense', '25.00', 'Suscripción',
            10, '2026-10-01'
        );

        $updated = $service->update(
            $space_id, $member_id, $recurring->id,
            $category_id, 'expense', '30.00',
            'Suscripción', 10, '2026-10-01'
        );

        $this->assertSame('30.00', $updated->amount);
    }

    /**
     * Comprueba que un usuario ajeno no puede editar.
     */
    public function testOutsiderCannotUpdate(): void {
        [$space_id, $owner_id, $category_id] = $this->prepareSpace();
        $outsider_id = $this->createUser();
        $service = new RecurringService();

        $recurring = $service->create(
            $space_id, $owner_id, $category_id,
            'expense', '10.00', 'Cuota',
            10, '2026-10-01'
        );

        $this->expectException(DomainException::class);

        $service->update(
            $space_id, $outsider_id, $recurring->id,
            $category_id, 'expense', '20.00',
            'Cuota', 10, '2026-10-01'
        );
    }

    /**
     * Comprueba que una membresía inactiva no permite desactivar.
     */
    public function testInactiveMemberCannotChangeStatus(): void {
        [$space_id, $owner_id, $category_id] = $this->prepareSpace();
        $member_id = $this->createUser();
        $service = new RecurringService();

        $recurring = $service->create(
            $space_id, $owner_id, $category_id,
            'expense', '10.00', 'Cuota',
            10, '2026-10-01'
        );

        $stmt = $this->db->prepare(
            'INSERT INTO `space_member`
             (id_space, id_user, left_at)
             VALUES (?, ?, NOW())'
        );
        $stmt->execute([$space_id, $member_id]);

        $this->expectException(DomainException::class);

        $service->setActive(
            $space_id, $member_id, $recurring->id, false
        );
    }

    /**
     * Comprueba que una plantilla de otro espacio no puede editarse.
     */
    public function testUpdateCannotCrossSpaces(): void {
        [$space_id, $owner_id, $category_id] = $this->prepareSpace();
        [$other_space_id, $other_owner_id, $other_category_id] =
            $this->prepareSpace();

        $service = new RecurringService();

        $other = $service->create(
            $other_space_id, $other_owner_id, $other_category_id,
            'expense', '50.00', 'Ajena',
            10, '2026-10-01'
        );

        $this->expectException(InvalidArgumentException::class);

        $service->update(
            $space_id, $owner_id, $other->id,
            $category_id, 'expense', '60.00',
            'Ajena', 10, '2026-10-01'
        );
    }

    /**
     * Comprueba que no puede cambiarse el estado de otra plantilla.
     */
    public function testStatusCannotCrossSpaces(): void {
        [$space_id, $owner_id] = $this->prepareSpace();
        [$other_space_id, $other_owner_id, $other_category_id] =
            $this->prepareSpace();

        $service = new RecurringService();
        $other = $service->create(
            $other_space_id, $other_owner_id, $other_category_id,
            'expense', '50.00', 'Ajena',
            10, '2026-10-01'
        );

        $this->expectException(InvalidArgumentException::class);

        $service->setActive(
            $space_id, $owner_id, $other->id, false
        );
    }

    /**
     * Crea un propietario, espacio y categoría de gasto.
     *
     * @return array{int, int, int}
     */
    private function prepareSpace(): array {
        $owner_id = $this->createUser();

        $space = (new SpaceService())->createSharedSpace(
            $owner_id,
            'Espacio recurrentes'
        );

        $this->assertNotNull($space->id);
        $this->spaces[] = $space->id;

        $category = (new CategoryService())->create(
            $space->id,
            $owner_id,
            'Hogar',
            'expense'
        );

        $this->assertNotNull($category->id);

        return [$space->id, $owner_id, $category->id];
    }

    /**
     * Crea un usuario único para integración.
     *
     * @return int Identificador.
     */
    private function createUser(): int {
        $stmt = $this->db->prepare(
            'INSERT INTO `user` (`name`, `email`, `password_hash`)
             VALUES (?, ?, ?)'
        );
        $stmt->execute([
            'Usuario recurrente',
            'recurring-' . bin2hex(random_bytes(12)) .
                '@example.test',
            password_hash('contraseña segura', PASSWORD_DEFAULT)
        ]);

        $id = (int) $this->db->lastInsertId();
        $this->users[] = $id;

        return $id;
    }
}

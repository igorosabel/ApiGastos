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

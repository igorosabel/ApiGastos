<?php declare(strict_types=1);

require_once dirname(__DIR__) . '/OrmIntegrationTestCase.php';

use Osumi\OsumiFramework\App\Service\Transaction\TransactionService;
use Osumi\OsumiFramework\App\Service\Category\CategoryService;
use Osumi\OsumiFramework\App\Service\Space\SpaceService;

final class TransactionServiceIntegrationTest extends OrmIntegrationTestCase {
    /** @var list<int> */
    private array $users = [];

    /** @var list<int> */
    private array $spaces = [];

    /**
     * Limpia los datos exclusivos de cada prueba.
     */
    protected function tearDown(): void {
        try {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            foreach (array_reverse($this->spaces) as $space_id) {
                foreach ([
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
     * Comprueba la persistencia exacta de un importe decimal.
     */
    public function testCreateExpenseWithExactAmount(): void {
        [$space_id, $owner_id] = $this->prepareSpace();
        $category_id = $this->category(
            $space_id,
            $owner_id,
            'expense'
        );

        $transaction = (new TransactionService())->create(
            $space_id,
            $owner_id,
            $category_id,
            'expense',
            '12345678.90',
            'Compra de prueba',
            '2026-10-15'
        );

        $this->assertNotNull($transaction->id);
        $this->assertSame('12345678.90', $transaction->amount);
        $this->assertSame($owner_id, $transaction->id_user);

        $stmt = $this->db->prepare(
            'SELECT amount, type, concept, date
             FROM `financial_transaction`
             WHERE id = ?'
        );
        $stmt->execute([$transaction->id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assertIsArray($row);
        $this->assertSame('12345678.90', $row['amount']);
        $this->assertSame('expense', $row['type']);
        $this->assertSame('Compra de prueba', $row['concept']);
        $this->assertSame('2026-10-15', $row['date']);
        $this->assertFalse($this->db->inTransaction());
    }

    /**
     * Comprueba que se puede registrar un ingreso.
     */
    public function testCreateIncome(): void {
        [$space_id, $owner_id] = $this->prepareSpace();
        $category_id = $this->category(
            $space_id,
            $owner_id,
            'income'
        );

        $transaction = (new TransactionService())->create(
            $space_id,
            $owner_id,
            $category_id,
            'income',
            '2500.00',
            null,
            '2026-10-14'
        );

        $this->assertSame('income', $transaction->type);
        $this->assertSame('2500.00', $transaction->amount);
        $this->assertNull($transaction->concept);
    }

    /**
     * Comprueba el orden de listado por fecha descendente.
     */
    public function testListTransactionsByDate(): void {
        [$space_id, $owner_id] = $this->prepareSpace();
        $category_id = $this->category(
            $space_id,
            $owner_id,
            'expense'
        );
        $service = new TransactionService();

        $first = $service->create(
            $space_id, $owner_id, $category_id,
            'expense', '10.00', 'Primero', '2026-10-01'
        );
        $second = $service->create(
            $space_id, $owner_id, $category_id,
            'expense', '20.00', 'Segundo', '2026-10-15'
        );

        $list = $service->getBySpace($space_id, $owner_id);

        $this->assertCount(2, $list);
        $this->assertSame($second->id, $list[0]->id);
        $this->assertSame($first->id, $list[1]->id);
    }

    /**
     * Comprueba que no se admite un importe cero.
     */
    public function testZeroAmountIsRejected(): void {
        [$space_id, $owner_id] = $this->prepareSpace();
        $category_id = $this->category(
            $space_id,
            $owner_id,
            'expense'
        );

        $this->expectException(InvalidArgumentException::class);

        (new TransactionService())->create(
            $space_id, $owner_id, $category_id,
            'expense', '0.00', null, '2026-10-15'
        );
    }

    /**
     * Comprueba que los importes no pierden precisión
     * ni admiten un formato decimal inadecuado.
     */
    public function testInvalidAmountFormats(): void {
        [$space_id, $owner_id] = $this->prepareSpace();
        $category_id = $this->category(
            $space_id,
            $owner_id,
            'expense'
        );
        $service = new TransactionService();

        foreach ([
            '-1.00',
            '1.234',
            '1,50',
            '1e3',
            '99999999999.00',
            '0.00'
        ] as $amount) {
            try {
                $service->create(
                    $space_id, $owner_id, $category_id,
                    'expense', $amount, null, '2026-10-15'
                );
                $this->fail("Se ha admitido el importe {$amount}.");
            }
            catch (InvalidArgumentException) {
                $this->assertFalse($this->db->inTransaction());
            }
        }
    }

    /**
     * Comprueba que las fechas inexistentes se rechazan.
     */
    public function testInvalidDate(): void {
        [$space_id, $owner_id] = $this->prepareSpace();
        $category_id = $this->category(
            $space_id,
            $owner_id,
            'expense'
        );

        $this->expectException(InvalidArgumentException::class);

        (new TransactionService())->create(
            $space_id, $owner_id, $category_id,
            'expense', '10.00', null, '2026-02-30'
        );
    }

    /**
     * Comprueba que un movimiento no utiliza
     * una categoría de tipo incompatible.
     */
    public function testCategoryTypeMismatch(): void {
        [$space_id, $owner_id] = $this->prepareSpace();
        $category_id = $this->category(
            $space_id,
            $owner_id,
            'income'
        );

        $this->expectException(InvalidArgumentException::class);

        (new TransactionService())->create(
            $space_id, $owner_id, $category_id,
            'expense', '10.00', null, '2026-10-15'
        );
    }

    /**
     * Comprueba que no se utiliza una categoría
     * perteneciente a otro espacio.
     */
    public function testCategoryFromAnotherSpace(): void {
        [$space_id, $owner_id] = $this->prepareSpace();
        [$other_space_id, $other_owner_id] = $this->prepareSpace();
        $category_id = $this->category(
            $other_space_id,
            $other_owner_id,
            'expense'
        );

        $this->expectException(InvalidArgumentException::class);

        (new TransactionService())->create(
            $space_id, $owner_id, $category_id,
            'expense', '10.00', null, '2026-10-15'
        );
    }

    /**
     * Comprueba que un usuario ajeno no registra movimientos.
     */
    public function testOutsiderCannotCreate(): void {
        [$space_id, $owner_id] = $this->prepareSpace();
        $outsider_id = $this->createUser();
        $category_id = $this->category(
            $space_id,
            $owner_id,
            'expense'
        );

        $this->expectException(DomainException::class);

        (new TransactionService())->create(
            $space_id, $outsider_id, $category_id,
            'expense', '10.00', null, '2026-10-15'
        );
    }

    /**
     * Comprueba que una membresía histórica
     * no permite crear movimientos.
     */
    public function testInactiveMemberCannotCreate(): void {
        [$space_id, $owner_id] = $this->prepareSpace();
        $guest_id = $this->createUser();
        $category_id = $this->category(
            $space_id,
            $owner_id,
            'expense'
        );

        $stmt = $this->db->prepare(
            'INSERT INTO `space_member`
             (id_space, id_user, left_at)
             VALUES (?, ?, NOW())'
        );
        $stmt->execute([$space_id, $guest_id]);

        $this->expectException(DomainException::class);

        (new TransactionService())->create(
            $space_id, $guest_id, $category_id,
            'expense', '10.00', null, '2026-10-15'
        );
    }

    /**
     * Comprueba que un usuario ajeno no consulta movimientos.
     */
    public function testOutsiderCannotList(): void {
        [$space_id] = $this->prepareSpace();
        $outsider_id = $this->createUser();

        $this->expectException(DomainException::class);

        (new TransactionService())->getBySpace(
            $space_id,
            $outsider_id
        );
    }

    /**
     * Comprueba los valores por defecto de paginación.
     */
    public function testSearchDefaultPagination(): void {
        [$space_id, $owner_id] = $this->prepareSpace();
        $category_id = $this->category(
            $space_id, $owner_id, 'expense'
        );

        $service = new TransactionService();

        $created = $service->create(
            $space_id, $owner_id, $category_id,
            'expense', '12.50', 'Prueba', '2026-10-15'
        );

        $result = $service->search($space_id, $owner_id);

        $this->assertSame(1, $result['total']);
        $this->assertSame(1, $result['page']);
        $this->assertSame(20, $result['page_size']);
        $this->assertSame(1, $result['total_pages']);
        $this->assertCount(1, $result['transactions']);
        $this->assertSame(
            $created->id,
            $result['transactions'][0]->id
        );
        $this->assertSame(
            '12.50',
            $result['transactions'][0]->amount
        );
    }

    /**
     * Comprueba la separación de páginas y el desempate por ID.
     */
    public function testSearchPagesAndStableOrdering(): void {
        [$space_id, $owner_id] = $this->prepareSpace();
        $category_id = $this->category(
            $space_id, $owner_id, 'expense'
        );
        $service = new TransactionService();

        $ids = [];

        foreach (['10.00', '20.00', '30.00', '40.00', '50.00'] as $amount) {
            $transaction = $service->create(
                $space_id, $owner_id, $category_id,
                'expense', $amount, null, '2026-10-15'
            );
            $ids[] = $transaction->id;
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

        $this->assertSame(
            [$ids[4], $ids[3]],
            array_map(
                static fn($item) => $item->id,
                $first['transactions']
            )
        );
        $this->assertSame(
            [$ids[2], $ids[1]],
            array_map(
                static fn($item) => $item->id,
                $second['transactions']
            )
        );
        $this->assertSame(
            [$ids[0]],
            array_map(
                static fn($item) => $item->id,
                $third['transactions']
            )
        );
    }

    /**
     * Comprueba que las fechas inicial y final son inclusivas.
     */
    public function testSearchDateRangeIsInclusive(): void {
        [$space_id, $owner_id] = $this->prepareSpace();
        $category_id = $this->category(
            $space_id, $owner_id, 'expense'
        );
        $service = new TransactionService();

        foreach ([
            '2026-09-30',
            '2026-10-01',
            '2026-10-15',
            '2026-10-31',
            '2026-11-01'
        ] as $date) {
            $service->create(
                $space_id, $owner_id, $category_id,
                'expense', '1.00', null, $date
            );
        }

        $result = $service->search(
            $space_id,
            $owner_id,
            date_from: '2026-10-01',
            date_to: '2026-10-31'
        );

        $this->assertSame(3, $result['total']);

        $this->assertSame(
            ['2026-10-31', '2026-10-15', '2026-10-01'],
            array_map(
                static fn($item) => $item->date,
                $result['transactions']
            )
        );
    }

    /**
     * Comprueba que se combinan filtros de tipo y categoría.
     */
    public function testSearchCombinesTypeAndCategory(): void {
        [$space_id, $owner_id] = $this->prepareSpace();

        $service = new TransactionService();
        $expense_a = $this->category(
            $space_id, $owner_id, 'expense'
        );
        $expense_b = $this->category(
            $space_id, $owner_id, 'expense'
        );
        $income = $this->category(
            $space_id, $owner_id, 'income'
        );

        $expected = $service->create(
            $space_id, $owner_id, $expense_a,
            'expense', '12.00', null, '2026-10-15'
        );
        $service->create(
            $space_id, $owner_id, $expense_b,
            'expense', '15.00', null, '2026-10-15'
        );
        $service->create(
            $space_id, $owner_id, $income,
            'income', '100.00', null, '2026-10-15'
        );

        $result = $service->search(
            $space_id,
            $owner_id,
            type: 'expense',
            category_id: $expense_a
        );

        $this->assertSame(1, $result['total']);
        $this->assertSame(
            $expected->id,
            $result['transactions'][0]->id
        );
    }

    /**
     * Comprueba que los movimientos no se mezclan entre espacios.
     */
    public function testSearchDoesNotMixSpaces(): void {
        [$space_id, $owner_id] = $this->prepareSpace();
        [$other_space_id, $other_owner_id] = $this->prepareSpace();

        $first_category = $this->category(
            $space_id, $owner_id, 'expense'
        );
        $second_category = $this->category(
            $other_space_id, $other_owner_id, 'expense'
        );
        $service = new TransactionService();

        $service->create(
            $space_id, $owner_id, $first_category,
            'expense', '10.00', null, '2026-10-15'
        );
        $service->create(
            $other_space_id, $other_owner_id, $second_category,
            'expense', '20.00', null, '2026-10-15'
        );

        $result = $service->search($space_id, $owner_id);

        $this->assertSame(1, $result['total']);
        $this->assertSame(
            $space_id,
            $result['transactions'][0]->id_space
        );
    }

    /**
     * Comprueba los parámetros inválidos de consulta.
     */
    public function testSearchRejectsInvalidFilters(): void {
        [$space_id, $owner_id] = $this->prepareSpace();
        $service = new TransactionService();

        $invalid = [
            ['date_from' => '2026-02-30'],
            ['date_to' => '15/10/2026'],
            [
                'date_from' => '2026-11-01',
                'date_to' => '2026-10-01'
            ],
            ['type' => 'other'],
            ['category_id' => -1],
            ['page' => 0],
            ['page' => 100001],
            ['page_size' => 0],
            ['page_size' => 101]
        ];

        foreach ($invalid as $filters) {
            try {
                $service->search(
                    $space_id,
                    $owner_id,
                    ...$filters
                );
                $this->fail(
                    'Se ha admitido un filtro inválido: ' .
                    json_encode($filters)
                );
            }
            catch (InvalidArgumentException) {
                $this->assertFalse($this->db->inTransaction());
            }
        }
    }

    /**
     * Comprueba que una página fuera de rango devuelve vacío.
     */
    public function testSearchOutsideLastPage(): void {
        [$space_id, $owner_id] = $this->prepareSpace();
        $category_id = $this->category(
            $space_id, $owner_id, 'expense'
        );
        $service = new TransactionService();

        $service->create(
            $space_id, $owner_id, $category_id,
            'expense', '1.00', null, '2026-10-15'
        );

        $result = $service->search(
            $space_id, $owner_id,
            page: 3, page_size: 1
        );

        $this->assertSame(1, $result['total']);
        $this->assertSame(1, $result['total_pages']);
        $this->assertSame([], $result['transactions']);
    }

    /**
     * Comprueba que no se consulta como usuario ajeno.
     */
    public function testOutsiderCannotSearch(): void {
        [$space_id] = $this->prepareSpace();
        $outsider_id = $this->createUser();

        $this->expectException(DomainException::class);

        (new TransactionService())->search(
            $space_id,
            $outsider_id
        );
    }

    /**
     * Comprueba que una baja revoca el acceso a la búsqueda.
     */
    public function testInactiveMemberCannotSearch(): void {
        [$space_id] = $this->prepareSpace();
        $guest_id = $this->createUser();

        $stmt = $this->db->prepare(
            'INSERT INTO `space_member`
             (id_space, id_user, left_at)
             VALUES (?, ?, NOW())'
        );
        $stmt->execute([$space_id, $guest_id]);

        $this->expectException(DomainException::class);

        (new TransactionService())->search(
            $space_id,
            $guest_id
        );
    }

    /**
     * Prepara un espacio con propietario autenticable.
     *
     * @return array{int, int} Identificadores.
     */
    private function prepareSpace(): array {
        $owner_id = $this->createUser();
        $space = (new SpaceService())->createSharedSpace(
            $owner_id,
            'Espacio de movimientos'
        );

        $this->assertNotNull($space->id);
        $this->spaces[] = $space->id;

        return [$space->id, $owner_id];
    }

    /**
     * Crea una categoría del tipo solicitado.
     *
     * @param int $space_id Espacio.
     * @param int $user_id Propietario.
     * @param string $type Tipo.
     * @return int Identificador.
     */
    private function category(
        int $space_id,
        int $user_id,
        string $type
    ): int {
        $category = (new CategoryService())->create(
            $space_id,
            $user_id,
            'Categoría ' . $type,
            $type
        );

        $this->assertNotNull($category->id);

        return $category->id;
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
            'Usuario movimientos',
            'transaction-test-' .
                bin2hex(random_bytes(12)) . '@example.test',
            password_hash('contraseña segura', PASSWORD_DEFAULT)
        ]);

        $id = (int) $this->db->lastInsertId();
        $this->users[] = $id;

        return $id;
    }
}

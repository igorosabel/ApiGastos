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

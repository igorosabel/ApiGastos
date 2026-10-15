<?php declare(strict_types=1);

require_once dirname(__DIR__) . '/OrmIntegrationTestCase.php';

use Osumi\OsumiFramework\App\Service\Category\CategoryService;
use Osumi\OsumiFramework\App\Service\Space\SpaceService;
use Osumi\OsumiFramework\App\Service\Transaction\TransactionService;

final class DeleteTransactionIntegrationTest extends OrmIntegrationTestCase {
    /** @var list<int> */
    private array $users = [];

    /** @var list<int> */
    private array $spaces = [];

    /**
     * Limpia todos los datos propios de cada prueba.
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
     * Comprueba el borrado definitivo de un movimiento.
     */
    public function testDeleteIndependentTransaction(): void {
        [$space_id, $owner_id, $category_id] = $this->prepareSpace();
        $service = new TransactionService();

        $transaction = $this->createTransaction(
            $space_id, $owner_id, $category_id
        );

        $service->delete(
            $space_id, $owner_id, $transaction->id
        );

        $this->assertSame(0, $this->countTransaction($transaction->id));
        $this->assertFalse($this->db->inTransaction());
    }

    /**
     * Comprueba que otro miembro activo puede borrar un
     * movimiento del espacio.
     */
    public function testActiveMemberCanDelete(): void {
        [$space_id, $owner_id, $category_id] = $this->prepareSpace();
        $member_id = $this->createUser();

        $stmt = $this->db->prepare(
            'INSERT INTO `space_member` (id_space, id_user)
             VALUES (?, ?)'
        );
        $stmt->execute([$space_id, $member_id]);

        $transaction = $this->createTransaction(
            $space_id, $owner_id, $category_id
        );

        (new TransactionService())->delete(
            $space_id, $member_id, $transaction->id
        );

        $this->assertSame(0, $this->countTransaction($transaction->id));
    }

    /**
     * Comprueba que el usuario ajeno no puede borrar.
     */
    public function testOutsiderCannotDelete(): void {
        [$space_id, $owner_id, $category_id] = $this->prepareSpace();
        $outsider_id = $this->createUser();

        $transaction = $this->createTransaction(
            $space_id, $owner_id, $category_id
        );

        try {
            (new TransactionService())->delete(
                $space_id, $outsider_id, $transaction->id
            );
            $this->fail('Se ha permitido borrar como usuario ajeno.');
        }
        catch (DomainException) {
            $this->assertSame(1, $this->countTransaction($transaction->id));
            $this->assertFalse($this->db->inTransaction());
        }
    }

    /**
     * Comprueba que un antiguo miembro no puede borrar.
     */
    public function testInactiveMemberCannotDelete(): void {
        [$space_id, $owner_id, $category_id] = $this->prepareSpace();
        $member_id = $this->createUser();

        $stmt = $this->db->prepare(
            'INSERT INTO `space_member` (id_space, id_user, left_at)
             VALUES (?, ?, NOW())'
        );
        $stmt->execute([$space_id, $member_id]);

        $transaction = $this->createTransaction(
            $space_id, $owner_id, $category_id
        );

        try {
            (new TransactionService())->delete(
                $space_id, $member_id, $transaction->id
            );
            $this->fail('Se ha permitido borrar como miembro inactivo.');
        }
        catch (DomainException) {
            $this->assertSame(1, $this->countTransaction($transaction->id));
        }
    }

    /**
     * Comprueba que no se elimina un movimiento de otro espacio.
     */
    public function testCannotDeleteFromAnotherSpace(): void {
        [$space_id, $owner_id] = $this->prepareSpace();
        [$other_space_id, $other_owner_id, $other_category_id] =
            $this->prepareSpace();

        $transaction = $this->createTransaction(
            $other_space_id, $other_owner_id, $other_category_id
        );

        try {
            (new TransactionService())->delete(
                $space_id, $owner_id, $transaction->id
            );
            $this->fail('Se ha permitido borrar un movimiento ajeno.');
        }
        catch (InvalidArgumentException) {
            $this->assertSame(1, $this->countTransaction($transaction->id));
        }
    }

    /**
     * Comprueba que no puede eliminarse dos veces.
     */
    public function testCannotDeleteTwice(): void {
        [$space_id, $owner_id, $category_id] = $this->prepareSpace();
        $transaction = $this->createTransaction(
            $space_id, $owner_id, $category_id
        );
        $service = new TransactionService();

        $service->delete($space_id, $owner_id, $transaction->id);

        $this->expectException(InvalidArgumentException::class);

        $service->delete($space_id, $owner_id, $transaction->id);
    }

    /**
     * Comprueba que no puede eliminarse un movimiento
     * de una ocurrencia recurrente confirmada.
     */
    public function testCannotDeleteConfirmedRecurringTransaction(): void {
        [$space_id, $owner_id, $category_id] = $this->prepareSpace();

        $transaction = $this->createTransaction(
            $space_id, $owner_id, $category_id
        );

        $stmt = $this->db->prepare(
            'INSERT INTO `recurring_transaction`
             (id_space, id_category, type, amount, concept,
              day_of_month, start_date, active)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $space_id, $category_id, 'expense', '25.00',
            'Alquiler', 15, '2026-10-01', 1
        ]);

        $recurring_id = (int) $this->db->lastInsertId();

        $stmt = $this->db->prepare(
            'INSERT INTO `recurring_occurrence`
             (id_space, id_recurring_transaction, period,
              scheduled_date, status, id_category, type,
              concept, amount, id_transaction, resolved_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
        );
        $stmt->execute([
            $space_id,
            $recurring_id,
            '2026-10-01',
            '2026-10-15',
            'confirmed',
            $category_id,
            'expense',
            'Alquiler',
            '25.00',
            $transaction->id
        ]);

        try {
            (new TransactionService())->delete(
                $space_id, $owner_id, $transaction->id
            );
            $this->fail(
                'Se ha eliminado un movimiento recurrente confirmado.'
            );
        }
        catch (LogicException) {
            $this->assertSame(1, $this->countTransaction($transaction->id));
            $this->assertFalse($this->db->inTransaction());

            $stmt = $this->db->prepare(
                'SELECT COUNT(*) FROM `recurring_occurrence`
                 WHERE id_transaction = ?'
            );
            $stmt->execute([$transaction->id]);

            $this->assertSame(1, (int) $stmt->fetchColumn());
        }
    }

    /**
     * Comprueba que los identificadores inválidos se rechazan.
     */
    public function testInvalidIdentifiers(): void {
        $this->expectException(InvalidArgumentException::class);

        (new TransactionService())->delete(0, 0, 0);
    }

    /**
     * Crea un espacio y una categoría de gasto.
     *
     * @return array{int, int, int}
     */
    private function prepareSpace(): array {
        $owner_id = $this->createUser();

        $space = (new SpaceService())->createSharedSpace(
            $owner_id,
            'Espacio prueba eliminación'
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
     * Crea un movimiento de gasto para la prueba.
     *
     * @param int $space_id Identificador del espacio.
     * @param int $user_id Propietario.
     * @param int $category_id Categoría.
     * @return \Osumi\OsumiFramework\App\Model\FinancialTransaction
     */
    private function createTransaction(
        int $space_id,
        int $user_id,
        int $category_id
    ): \Osumi\OsumiFramework\App\Model\FinancialTransaction {
        return (new TransactionService())->create(
            $space_id,
            $user_id,
            $category_id,
            'expense',
            '25.00',
            'Alquiler',
            '2026-10-15'
        );
    }

    /**
     * Crea un usuario de integración.
     *
     * @return int Identificador creado.
     */
    private function createUser(): int {
        $stmt = $this->db->prepare(
            'INSERT INTO `user` (`name`, `email`, `password_hash`)
             VALUES (?, ?, ?)'
        );

        $stmt->execute([
            'Usuario eliminación',
            'delete-transaction-' .
                bin2hex(random_bytes(12)) . '@example.test',
            password_hash('contraseña segura', PASSWORD_DEFAULT)
        ]);

        $id = (int) $this->db->lastInsertId();
        $this->users[] = $id;

        return $id;
    }

    /**
     * Cuenta la persistencia de un movimiento.
     *
     * @param int $transaction_id Identificador del movimiento.
     * @return int Número de registros.
     */
    private function countTransaction(int $transaction_id): int {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM `financial_transaction`
             WHERE id = ?'
        );
        $stmt->execute([$transaction_id]);

        return (int) $stmt->fetchColumn();
    }
}

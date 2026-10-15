<?php declare(strict_types=1);

require_once dirname(__DIR__) . '/OrmIntegrationTestCase.php';

use Osumi\OsumiFramework\App\Service\Space\SpaceService;

final class SpaceServiceIntegrationTest extends OrmIntegrationTestCase {
    /** @var list<int> */
    private array $user_ids = [];

    /** @var list<int> */
    private array $space_ids = [];

    /**
     * Elimina los datos generados por los tests.
     */
    protected function tearDown(): void {
        try {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            foreach (array_reverse($this->space_ids) as $space_id) {
                $stmt = $this->db->prepare(
                    'DELETE FROM `space_member` WHERE id_space = ?'
                );
                $stmt->execute([$space_id]);

                $stmt = $this->db->prepare(
                    'DELETE FROM `space` WHERE id = ?'
                );
                $stmt->execute([$space_id]);
            }

            foreach (array_reverse($this->user_ids) as $user_id) {
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
     * Comprueba que se crean el espacio y su membresía.
     */
    public function testCreateSharedSpace(): void {
        $user_id = $this->createUser();
        $service = new SpaceService();

        $space = $service->createSharedSpace(
            $user_id,
            '  Familia  '
        );

        $this->assertNotNull($space->id);
        $this->space_ids[] = $space->id;

        $this->assertSame('Familia', $space->name);
        $this->assertSame('shared', $space->type);
        $this->assertSame($user_id, $space->owner_id);
        $this->assertNull($space->personal_owner_id);

        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM `space_member`
             WHERE id_space = ? AND id_user = ?'
        );
        $stmt->execute([$space->id, $user_id]);

        $this->assertSame(1, (int) $stmt->fetchColumn());
        $this->assertFalse($this->db->inTransaction());
    }

    /**
     * Comprueba que un usuario solo obtiene espacios de los
     * que es miembro.
     */
    public function testGetUserSpaces(): void {
        $user_a = $this->createUser();
        $user_b = $this->createUser();
        $service = new SpaceService();

        $own_space = $service->createSharedSpace(
            $user_a,
            'Espacio A'
        );
        $other_space = $service->createSharedSpace(
            $user_b,
            'Espacio B'
        );

        $this->space_ids[] = $own_space->id;
        $this->space_ids[] = $other_space->id;

        $spaces = $service->getUserSpaces($user_a);

        $this->assertCount(1, $spaces);
        $this->assertSame($own_space->id, $spaces[0]->id);
        $this->assertSame('Espacio A', $spaces[0]->name);
        $this->assertNotSame($other_space->id, $spaces[0]->id);
    }

    /**
     * Comprueba que un fallo al insertar la membresía
     * revierte también la creación del espacio.
     */
    public function testRollbackWhenMembershipFails(): void {
        $user_id = $this->createUser();
        $created_space_id = null;
        $db = $this->db;

        $proxy = $this->createStub(PDO::class);

        $proxy->method('prepare')->willReturnCallback(
            static function(string $sql) use (
                $db,
                &$created_space_id
            ): PDOStatement|false {
                if (str_contains(
                    $sql,
                    'INSERT INTO `space_member`'
                )) {
                    throw new PDOException(
                        'Fallo simulado al crear la membresía.'
                    );
                }

                return $db->prepare($sql);
            }
        );

        $proxy->method('beginTransaction')->willReturnCallback(
            static fn(): bool => $db->beginTransaction()
        );

        $proxy->method('commit')->willReturnCallback(
            static fn(): bool => $db->commit()
        );

        $proxy->method('rollBack')->willReturnCallback(
            static fn(): bool => $db->rollBack()
        );

        $proxy->method('inTransaction')->willReturnCallback(
            static fn(): bool => $db->inTransaction()
        );

        $proxy->method('lastInsertId')->willReturnCallback(
            static function() use (
                $db,
                &$created_space_id
            ): string|false {
                $id = $db->lastInsertId();
                $created_space_id = (int) $id;
                return $id;
            }
        );

        $this->setOrmConnection($proxy);

        $service = new SpaceService();

        try {
            $service->createSharedSpace(
                $user_id,
                'Espacio rollback'
            );

            $this->fail(
                'La creación debería haber fallado.'
            );
        }
        catch (PDOException $e) {
            $this->assertSame(
                'Fallo simulado al crear la membresía.',
                $e->getMessage()
            );
        }

        $this->assertNotNull($created_space_id);
        $this->assertFalse($db->inTransaction());

        $stmt = $db->prepare(
            'SELECT COUNT(*) FROM `space` WHERE id = ?'
        );
        $stmt->execute([$created_space_id]);
        $this->assertSame(0, (int) $stmt->fetchColumn());

        $stmt = $db->prepare(
            'SELECT COUNT(*) FROM `space_member`
             WHERE id_space = ?'
        );
        $stmt->execute([$created_space_id]);
        $this->assertSame(0, (int) $stmt->fetchColumn());
    }

    /**
     * Crea un usuario exclusivamente para la prueba.
     *
     * @return int Identificador creado.
     */
    private function createUser(): int {
        $stmt = $this->db->prepare(
            'INSERT INTO `user` (`name`, `email`, `password_hash`)
             VALUES (?, ?, ?)'
        );

        $stmt->execute([
            'Usuario integración',
            'space-test-' . bin2hex(random_bytes(12)) . '@example.test',
            password_hash('contraseña segura', PASSWORD_DEFAULT)
        ]);

        $user_id = (int) $this->db->lastInsertId();
        $this->user_ids[] = $user_id;

        return $user_id;
    }
}

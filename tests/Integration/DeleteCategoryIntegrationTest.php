<?php declare(strict_types=1);

require_once __DIR__ . '/OrmIntegrationTestCase.php';

use Osumi\OsumiFramework\App\Service\Category\CategoryService;
use Osumi\OsumiFramework\App\Service\Space\SpaceService;

final class DeleteCategoryIntegrationTest extends OrmIntegrationTestCase {
    /** @var list<int> */
    private array $users = [];

    /** @var list<int> */
    private array $spaces = [];

    /**
     * Elimina los datos creados en cada prueba.
     */
    protected function tearDown(): void {
        try {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            foreach (array_reverse($this->spaces) as $space_id) {
                // Eliminar primero los descendientes.
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

                $stmt = $this->db->prepare(
                    'SELECT COUNT(*) FROM `category`
                     WHERE id_space = ?'
                );
                $stmt->execute([$space_id]);

                $this->assertSame(0, (int) $stmt->fetchColumn());

                $stmt = $this->db->prepare(
                    'DELETE FROM `space_member` WHERE id_space = ?'
                );
                $stmt->execute([$space_id]);

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
     * Comprueba que puede eliminarse una categoría sin dependencias.
     */
    public function testDeleteEmptyCategory(): void {
        [$space_id, $owner_id] = $this->prepareSpace();
        $service = new CategoryService();

        $category = $service->create(
            $space_id, $owner_id, 'Temporal', 'expense'
        );

        $service->delete($space_id, $owner_id, $category->id);

        $this->assertFalse($this->db->inTransaction());
        $this->assertSame(0, $this->countCategory($category->id));
    }

    /**
     * Comprueba que no se elimina una categoría con hijos.
     */
    public function testCategoryWithChildrenCannotBeDeleted(): void {
        [$space_id, $owner_id] = $this->prepareSpace();
        $service = new CategoryService();

        $parent = $service->create(
            $space_id, $owner_id, 'Padre', 'expense'
        );
        $child = $service->create(
            $space_id, $owner_id, 'Hijo', 'expense', $parent->id
        );

        try {
            $service->delete($space_id, $owner_id, $parent->id);
            $this->fail('Se ha eliminado una categoría con hijos.');
        }
        catch (LogicException) {
            $this->assertSame(1, $this->countCategory($parent->id));
            $this->assertSame(1, $this->countCategory($child->id));
            $this->assertFalse($this->db->inTransaction());
        }
    }

    /**
     * Comprueba que una hoja puede eliminarse antes de su padre.
     */
    public function testDeleteChildThenParent(): void {
        [$space_id, $owner_id] = $this->prepareSpace();
        $service = new CategoryService();

        $parent = $service->create(
            $space_id, $owner_id, 'Padre', 'expense'
        );
        $child = $service->create(
            $space_id, $owner_id, 'Hijo', 'expense', $parent->id
        );

        $service->delete($space_id, $owner_id, $child->id);
        $service->delete($space_id, $owner_id, $parent->id);

        $this->assertSame(0, $this->countCategory($child->id));
        $this->assertSame(0, $this->countCategory($parent->id));
    }

    /**
     * Comprueba que un usuario ajeno no puede eliminar categorías.
     */
    public function testOutsiderCannotDelete(): void {
        [$space_id, $owner_id] = $this->prepareSpace();
        $outsider_id = $this->createUser();
        $service = new CategoryService();

        $category = $service->create(
            $space_id, $owner_id, 'Privada', 'expense'
        );

        $this->expectException(DomainException::class);

        $service->delete(
            $space_id,
            $outsider_id,
            $category->id
        );
    }

    /**
     * Comprueba que un antiguo miembro no conserva permisos.
     */
    public function testInactiveMemberCannotDelete(): void {
        [$space_id, $owner_id] = $this->prepareSpace();
        $member_id = $this->createUser();
        $service = new CategoryService();

        $category = $service->create(
            $space_id, $owner_id, 'Protegida', 'expense'
        );

        $stmt = $this->db->prepare(
            'INSERT INTO `space_member`
             (id_space, id_user, left_at)
             VALUES (?, ?, NOW())'
        );
        $stmt->execute([$space_id, $member_id]);

        $this->expectException(DomainException::class);

        $service->delete(
            $space_id,
            $member_id,
            $category->id
        );
    }

    /**
     * Comprueba el aislamiento entre espacios.
     */
    public function testCannotDeleteCategoryFromAnotherSpace(): void {
        [$space_id, $owner_id] = $this->prepareSpace();
        [$other_space_id, $other_owner_id] = $this->prepareSpace();
        $service = new CategoryService();

        $category = $service->create(
            $other_space_id,
            $other_owner_id,
            'Ajena',
            'income'
        );

        $this->expectException(InvalidArgumentException::class);

        $service->delete(
            $space_id,
            $owner_id,
            $category->id
        );
    }

    /**
     * Comprueba el rechazo de identificadores inválidos.
     */
    public function testInvalidIdentifiers(): void {
        $this->expectException(InvalidArgumentException::class);

        (new CategoryService())->delete(0, 0, 0);
    }

    /**
     * Crea un espacio compartido y su propietario.
     *
     * @return array{int, int} Identificadores.
     */
    private function prepareSpace(): array {
        $owner_id = $this->createUser();

        $space = (new SpaceService())->createSharedSpace(
            $owner_id,
            'Espacio categorías'
        );

        $this->assertNotNull($space->id);
        $this->spaces[] = $space->id;

        return [$space->id, $owner_id];
    }

    /**
     * Crea un usuario de pruebas.
     *
     * @return int Identificador.
     */
    private function createUser(): int {
        $stmt = $this->db->prepare(
            'INSERT INTO `user` (`name`, `email`, `password_hash`)
             VALUES (?, ?, ?)'
        );

        $stmt->execute([
            'Usuario categorías',
            'delete-category-' . bin2hex(random_bytes(12)) .
                '@example.test',
            password_hash('contraseña segura', PASSWORD_DEFAULT)
        ]);

        $id = (int) $this->db->lastInsertId();
        $this->users[] = $id;

        return $id;
    }

    /**
     * Cuenta las filas existentes de una categoría concreta.
     *
     * @param int $category_id Identificador.
     * @return int Número de registros.
     */
    private function countCategory(int $category_id): int {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM `category` WHERE id = ?'
        );
        $stmt->execute([$category_id]);

        return (int) $stmt->fetchColumn();
    }
}

<?php declare(strict_types=1);

require_once dirname(__DIR__) . '/OrmIntegrationTestCase.php';

use Osumi\OsumiFramework\App\Service\Category\CategoryService;
use Osumi\OsumiFramework\App\Service\Space\SpaceMemberService;
use Osumi\OsumiFramework\App\Service\Space\SpaceService;

final class CategoryServiceIntegrationTest extends OrmIntegrationTestCase {
    /** @var list<int> */
    private array $user_ids = [];

    /** @var list<int> */
    private array $space_ids = [];

    /**
     * Limpia los datos creados en cada prueba.
     *
     * Elimina las categorías desde las hojas hacia las raíces
     * para respetar la clave foránea autorreferenciada.
     */
    protected function tearDown(): void {
        try {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            foreach (array_reverse($this->space_ids) as $space_id) {
                // Eliminar primero las categorías que no tienen hijos.
                // Repetir hasta vaciar el árbol del espacio.
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

                // Comprobar que no quedan categorías sin eliminar.
                $stmt = $this->db->prepare(
                    'SELECT COUNT(*) FROM `category`
                     WHERE id_space = ?'
                );
                $stmt->execute([$space_id]);

                $this->assertSame(
                    0,
                    (int) $stmt->fetchColumn(),
                    'Quedan categorías sin eliminar.'
                );

                // Eliminar las membresías del espacio.
                $stmt = $this->db->prepare(
                    'DELETE FROM `space_member`
                     WHERE id_space = ?'
                );
                $stmt->execute([$space_id]);

                // Eliminar el espacio.
                $stmt = $this->db->prepare(
                    'DELETE FROM `space`
                     WHERE id = ?'
                );
                $stmt->execute([$space_id]);
            }

            // Eliminar los usuarios creados por la prueba.
            foreach (array_reverse($this->user_ids) as $user_id) {
                $stmt = $this->db->prepare(
                    'DELETE FROM `user`
                     WHERE id = ?'
                );
                $stmt->execute([$user_id]);
            }
        }
        finally {
            parent::tearDown();
        }
    }

    /**
     * Comprueba la creación de una categoría raíz y una hija.
     */
    public function testCreateRootAndChild(): void {
        [$space_id, $owner_id] = $this->prepareSpace();
        $service = new CategoryService();

        $root = $service->create(
            $space_id, $owner_id, 'Alimentación', 'expense'
        );

        $child = $service->create(
            $space_id, $owner_id, 'Supermercado',
            'expense', $root->id, 1
        );

        $this->assertNotNull($root->id);
        $this->assertNull($root->id_parent);
        $this->assertSame($root->id, $child->id_parent);
        $this->assertSame(1, $child->sort_order);

        $stmt = $this->db->prepare(
            'SELECT id_parent, name FROM `category` WHERE id = ?'
        );
        $stmt->execute([$child->id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assertIsArray($row);
        $this->assertSame((string) $root->id, (string) $row['id_parent']);
        $this->assertSame('Supermercado', $row['name']);
    }

    /**
     * Comprueba la consulta de categorías del espacio.
     */
    public function testListCategories(): void {
        [$space_id, $owner_id] = $this->prepareSpace();
        $service = new CategoryService();

        $service->create(
            $space_id, $owner_id, 'Hogar', 'expense'
        );
        $service->create(
            $space_id, $owner_id, 'Sueldo', 'income'
        );

        $list = $service->getBySpace($space_id, $owner_id);

        $this->assertCount(2, $list);

        foreach ($list as $category) {
            $this->assertSame($space_id, $category->id_space);
        }
    }

    /**
     * Comprueba que no se accede a categorías de otro espacio.
     */
    public function testAnotherSpaceIsForbidden(): void {
        [$space_id, $owner_id] = $this->prepareSpace();
        $other_user_id = $this->createUser();
        $service = new CategoryService();

        $this->expectException(DomainException::class);

        $service->getBySpace($space_id, $other_user_id);
    }

    /**
     * Comprueba que un miembro inactivo no puede crear categorías.
     */
    public function testInactiveMemberCannotCreate(): void {
        [$space_id, $owner_id] = $this->prepareSpace();
        $member_id = $this->createUser();

        $stmt = $this->db->prepare(
            'INSERT INTO `space_member`
             (id_space, id_user, left_at)
             VALUES (?, ?, NOW())'
        );
        $stmt->execute([$space_id, $member_id]);

        $this->expectException(DomainException::class);

        (new CategoryService())->create(
            $space_id, $member_id, 'No permitida', 'expense'
        );
    }

    /**
     * Comprueba que el padre debe pertenecer al mismo espacio.
     */
    public function testParentFromAnotherSpaceIsRejected(): void {
        [$space_id, $owner_id] = $this->prepareSpace();
        [$other_space_id, $other_owner_id] = $this->prepareSpace();
        $service = new CategoryService();

        $parent = $service->create(
            $other_space_id, $other_owner_id,
            'Otra categoría', 'expense'
        );

        $this->expectException(InvalidArgumentException::class);

        $service->create(
            $space_id, $owner_id, 'Hija', 'expense', $parent->id
        );
    }

    /**
     * Comprueba que no se mezclan categorías de distinto tipo.
     */
    public function testParentTypeMustMatch(): void {
        [$space_id, $owner_id] = $this->prepareSpace();
        $service = new CategoryService();

        $parent = $service->create(
            $space_id, $owner_id, 'Ingresos', 'income'
        );

        $this->expectException(InvalidArgumentException::class);

        $service->create(
            $space_id, $owner_id,
            'Hija incorrecta', 'expense', $parent->id
        );
    }

    /**
     * Comprueba validaciones básicas de negocio.
     */
    public function testInvalidTypeIsRejected(): void {
        [$space_id, $owner_id] = $this->prepareSpace();

        $this->expectException(InvalidArgumentException::class);

        (new CategoryService())->create(
            $space_id, $owner_id, 'Prueba', 'other'
        );
    }

    /**
     * Comprueba la edición de nombre y orden.
     */
    public function testUpdateNameAndOrder(): void {
        [$space_id, $owner_id] = $this->prepareSpace();
        $service = new CategoryService();

        $category = $service->create(
            $space_id, $owner_id, 'Casa', 'expense'
        );

        $updated = $service->update(
            $space_id, $owner_id, $category->id,
            'Hogar', null, 4
        );

        $this->assertSame($category->id, $updated->id);
        $this->assertSame('Hogar', $updated->name);
        $this->assertNull($updated->id_parent);
        $this->assertSame(4, $updated->sort_order);
        $this->assertSame('expense', $updated->default_type);
        $this->assertFalse($this->db->inTransaction());
    }

    /**
     * Comprueba que una categoría puede cambiar de padre.
     */
    public function testMoveCategoryToAnotherParent(): void {
        [$space_id, $owner_id] = $this->prepareSpace();
        $service = new CategoryService();

        $first = $service->create(
            $space_id, $owner_id, 'Alimentación', 'expense'
        );
        $second = $service->create(
            $space_id, $owner_id, 'Hogar', 'expense'
        );
        $child = $service->create(
            $space_id, $owner_id, 'Compras',
            'expense', $first->id
        );

        $updated = $service->update(
            $space_id, $owner_id, $child->id,
            'Compras', $second->id, 1
        );

        $this->assertSame($second->id, $updated->id_parent);

        $stmt = $this->db->prepare(
            'SELECT id_parent FROM `category` WHERE id = ?'
        );
        $stmt->execute([$child->id]);

        $this->assertSame(
            (string) $second->id,
            (string) $stmt->fetchColumn()
        );
    }

    /**
     * Comprueba que no puede asignarse a sí misma como padre.
     */
    public function testCannotBeOwnParent(): void {
        [$space_id, $owner_id] = $this->prepareSpace();
        $service = new CategoryService();
        $category = $service->create(
            $space_id, $owner_id, 'Hogar', 'expense'
        );

        $this->expectException(InvalidArgumentException::class);

        $service->update(
            $space_id, $owner_id, $category->id,
            'Hogar', $category->id, 0
        );
    }

    /**
     * Comprueba la detección de ciclos indirectos.
     */
    public function testCannotCreateIndirectCycle(): void {
        [$space_id, $owner_id] = $this->prepareSpace();
        $service = new CategoryService();

        $root = $service->create(
            $space_id, $owner_id, 'Raíz', 'expense'
        );
        $child = $service->create(
            $space_id, $owner_id, 'Hija',
            'expense', $root->id
        );
        $grandchild = $service->create(
            $space_id, $owner_id, 'Nieta',
            'expense', $child->id
        );

        try {
            $service->update(
                $space_id, $owner_id, $root->id,
                'Raíz', $grandchild->id, 0
            );

            $this->fail('Se ha permitido crear un ciclo.');
        }
        catch (InvalidArgumentException) {
            $stmt = $this->db->prepare(
                'SELECT id_parent FROM `category` WHERE id = ?'
            );
            $stmt->execute([$root->id]);

            $this->assertNull($stmt->fetchColumn());
            $this->assertFalse($this->db->inTransaction());
        }
    }

    /**
     * Comprueba que no puede trasladarse una categoría
     * a un padre perteneciente a otro espacio.
     */
    public function testCannotMoveToAnotherSpace(): void {
        [$space_id, $owner_id] = $this->prepareSpace();
        [$other_space_id, $other_owner_id] = $this->prepareSpace();

        $service = new CategoryService();
        $category = $service->create(
            $space_id, $owner_id, 'Categoría', 'expense'
        );
        $other_parent = $service->create(
            $other_space_id, $other_owner_id,
            'Otro padre', 'expense'
        );

        $this->expectException(InvalidArgumentException::class);

        $service->update(
            $space_id, $owner_id, $category->id,
            'Categoría', $other_parent->id, 0
        );
    }

    /**
     * Comprueba que no puede moverse una categoría
     * a un padre de diferente tipo.
     */
    public function testCannotMoveToDifferentType(): void {
        [$space_id, $owner_id] = $this->prepareSpace();

        $service = new CategoryService();
        $expense = $service->create(
            $space_id, $owner_id, 'Gastos', 'expense'
        );
        $income = $service->create(
            $space_id, $owner_id, 'Ingresos', 'income'
        );

        $this->expectException(InvalidArgumentException::class);

        $service->update(
            $space_id, $owner_id, $expense->id,
            'Gastos', $income->id, 0
        );
    }

    /**
     * Comprueba que un usuario ajeno no puede editar.
     */
    public function testOutsiderCannotUpdate(): void {
        [$space_id, $owner_id] = $this->prepareSpace();
        $outsider_id = $this->createUser();

        $service = new CategoryService();
        $category = $service->create(
            $space_id, $owner_id, 'Hogar', 'expense'
        );

        $this->expectException(DomainException::class);

        $service->update(
            $space_id, $outsider_id, $category->id,
            'Alterada', null, 0
        );
    }

    /**
     * Comprueba que un antiguo miembro no puede editar.
     */
    public function testInactiveMemberCannotUpdate(): void {
        [$space_id, $owner_id] = $this->prepareSpace();
        $member_id = $this->createUser();

        $stmt = $this->db->prepare(
            'INSERT INTO `space_member`
             (id_space, id_user, left_at)
             VALUES (?, ?, NOW())'
        );
        $stmt->execute([$space_id, $member_id]);

        $service = new CategoryService();
        $category = $service->create(
            $space_id, $owner_id, 'Hogar', 'expense'
        );

        $this->expectException(DomainException::class);

        $service->update(
            $space_id, $member_id, $category->id,
            'Alterada', null, 0
        );
    }

    /**
     * Prepara un propietario y un espacio compartido.
     *
     * @return array{int, int} Espacio y propietario.
     */
    private function prepareSpace(): array {
        $owner_id = $this->createUser();
        $space = (new SpaceService())->createSharedSpace(
            $owner_id,
            'Espacio de prueba'
        );

        $this->assertNotNull($space->id);
        $this->space_ids[] = $space->id;

        return [$space->id, $owner_id];
    }

    /**
     * Crea un usuario exclusivo para la prueba.
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
            'category-test-' . bin2hex(random_bytes(12)) . '@example.test',
            password_hash('contraseña segura', PASSWORD_DEFAULT)
        ]);

        $id = (int) $this->db->lastInsertId();
        $this->user_ids[] = $id;

        return $id;
    }
}

<?php declare(strict_types=1);

require_once __DIR__ . '/OrmIntegrationTestCase.php';

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
     */
    protected function tearDown(): void {
        try {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            foreach (array_reverse($this->space_ids) as $space_id) {
                $stmt = $this->db->prepare(
                  'SELECT id FROM `category`
                   WHERE id_space = ?
                   ORDER BY id DESC'
                );
                $stmt->execute([$space_id]);

                $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

                $delete = $this->db->prepare(
                  'DELETE FROM `category` WHERE id = ?'
                );

                foreach ($ids as $id) {
                  $delete->execute([$id]);
                }

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

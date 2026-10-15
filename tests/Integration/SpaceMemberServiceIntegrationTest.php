<?php declare(strict_types=1);

require_once __DIR__ . '/OrmIntegrationTestCase.php';

use Osumi\OsumiFramework\App\Model\SpaceMember;
use Osumi\OsumiFramework\App\Service\Space\SpaceMemberService;
use Osumi\OsumiFramework\App\Service\Space\SpaceService;

final class SpaceMemberServiceIntegrationTest extends OrmIntegrationTestCase {
    /** @var list<int> */
    private array $user_ids = [];

    /** @var list<int> */
    private array $space_ids = [];

    /**
     * Limpia los datos de esta prueba.
     */
    protected function tearDown(): void {
        try {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            foreach ($this->space_ids as $space_id) {
                $stmt = $this->db->prepare(
                    'DELETE FROM `space_member` WHERE id_space = ?'
                );
                $stmt->execute([$space_id]);

                $stmt = $this->db->prepare(
                    'DELETE FROM `space` WHERE id = ?'
                );
                $stmt->execute([$space_id]);
            }

            foreach ($this->user_ids as $user_id) {
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
     * Comprueba que el propietario obtiene los miembros del espacio.
     */
    public function testOwnerCanListMembers(): void {
        $owner_id = $this->createUser();
        $member_id = $this->createUser();

        $space = (new SpaceService())->createSharedSpace(
            $owner_id,
            'Familia prueba'
        );

        $this->assertNotNull($space->id);
        $this->space_ids[] = $space->id;

        $member = new SpaceMember();
        $member->id_space = $space->id;
        $member->id_user = $member_id;

        $this->assertTrue($member->save());

        $members = (new SpaceMemberService())->getMembers(
            $space->id,
            $owner_id
        );

        $this->assertCount(2, $members);

        $member_ids = array_map(
            static fn(SpaceMember $item): ?int => $item->id_user,
            $members
        );

        $this->assertContains($owner_id, $member_ids);
        $this->assertContains($member_id, $member_ids);
    }

    /**
     * Comprueba que un miembro no propietario no puede consultar.
     */
    public function testNonOwnerCannotListMembers(): void {
        $owner_id = $this->createUser();
        $member_id = $this->createUser();

        $space = (new SpaceService())->createSharedSpace(
            $owner_id,
            'Familia prueba'
        );

        $this->assertNotNull($space->id);
        $this->space_ids[] = $space->id;

        $this->expectException(DomainException::class);

        (new SpaceMemberService())->getMembers(
            $space->id,
            $member_id
        );
    }

    /**
     * Comprueba los identificadores inválidos.
     */
    public function testInvalidIds(): void {
        $this->expectException(InvalidArgumentException::class);

        (new SpaceMemberService())->getMembers(0, 0);
    }

    /**
     * Crea un usuario exclusivo para integración.
     *
     * @return int Identificador.
     */
    private function createUser(): int {
        $stmt = $this->db->prepare(
            'INSERT INTO `user` (`name`, `email`, `password_hash`)
             VALUES (?, ?, ?)'
        );

        $stmt->execute([
            'Usuario de prueba',
            'members-test-' . bin2hex(random_bytes(12)) . '@example.test',
            password_hash('contraseña segura', PASSWORD_DEFAULT)
        ]);

        $id = (int) $this->db->lastInsertId();
        $this->user_ids[] = $id;

        return $id;
    }
}

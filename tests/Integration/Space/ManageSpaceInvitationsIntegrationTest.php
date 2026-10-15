<?php declare(strict_types=1);

require_once dirname(__DIR__) . '/OrmIntegrationTestCase.php';

use Osumi\OsumiFramework\App\Model\Space;
use Osumi\OsumiFramework\App\Service\Space\SpaceInvitationService;
use Osumi\OsumiFramework\App\Service\Space\SpaceService;

final class ManageSpaceInvitationsIntegrationTest extends OrmIntegrationTestCase {
    /** @var list<int> */
    private array $users = [];

    /** @var list<int> */
    private array $spaces = [];

    /**
     * Limpia exclusivamente los registros creados por el test.
     */
    protected function tearDown(): void {
        try {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            foreach (array_reverse($this->spaces) as $space_id) {
                $stmt = $this->db->prepare(
                    'DELETE FROM `space_invitation` WHERE id_space = ?'
                );
                $stmt->execute([$space_id]);

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
     * Comprueba el listado y que no incluye invitaciones ajenas.
     */
    public function testListInvitationsBySpace(): void {
        [$service, $owner_id, $space] = $this->prepareSpace();
        [$other_service, $other_owner_id, $other_space] =
            $this->prepareSpace();

        $service->create(
            $space->id,
            $owner_id,
            'primero@example.test'
        );
        $service->create(
            $space->id,
            $owner_id,
            'segundo@example.test'
        );
        $other_service->create(
            $other_space->id,
            $other_owner_id,
            'tercero@example.test'
        );

        $list = $service->getBySpace($space->id, $owner_id);

        $this->assertCount(2, $list);
        $this->assertContainsOnlyInstancesOf(
            \Osumi\OsumiFramework\App\Model\SpaceInvitation::class,
            $list
        );

        foreach ($list as $invitation) {
            $this->assertSame($space->id, $invitation->id_space);
            $this->assertNotSame(
                $other_space->id,
                $invitation->id_space
            );
        }
    }

    /**
     * Comprueba que un usuario ajeno no puede listar invitaciones.
     */
    public function testNonOwnerCannotListInvitations(): void {
        [$service, $owner_id, $space] = $this->prepareSpace();
        $other_id = $this->createUser();

        $this->expectException(DomainException::class);

        $service->getBySpace($space->id, $other_id);
    }

    /**
     * Comprueba la revocación y su persistencia.
     */
    public function testRevokeInvitation(): void {
        [$service, $owner_id, $space] = $this->prepareSpace();

        $result = $service->create(
            $space->id,
            $owner_id,
            'invitado@example.test'
        );

        $invitation_id = $result['invitation']->id;
        $revoked = $service->revoke($invitation_id, $owner_id);

        $this->assertSame($invitation_id, $revoked->id);
        $this->assertNotNull($revoked->revoked_at);
        $this->assertNull($revoked->accepted_at);

        $stmt = $this->db->prepare(
            'SELECT revoked_at FROM `space_invitation` WHERE id = ?'
        );
        $stmt->execute([$invitation_id]);

        $this->assertNotNull($stmt->fetchColumn());
        $this->assertFalse($this->db->inTransaction());
    }

    /**
     * Comprueba que un usuario ajeno no puede revocar.
     */
    public function testNonOwnerCannotRevoke(): void {
        [$service, $owner_id, $space] = $this->prepareSpace();
        $other_id = $this->createUser();

        $result = $service->create(
            $space->id,
            $owner_id,
            'invitado@example.test'
        );

        $this->expectException(DomainException::class);

        $service->revoke($result['invitation']->id, $other_id);
    }

    /**
     * Comprueba que una invitación no puede revocarse dos veces.
     */
    public function testCannotRevokeTwice(): void {
        [$service, $owner_id, $space] = $this->prepareSpace();

        $result = $service->create(
            $space->id,
            $owner_id,
            'invitado@example.test'
        );

        $id = $result['invitation']->id;
        $service->revoke($id, $owner_id);

        $this->expectException(LogicException::class);

        $service->revoke($id, $owner_id);
    }

    /**
     * Comprueba que tampoco puede revocarse una invitación caducada.
     */
    public function testCannotRevokeExpiredInvitation(): void {
        [$service, $owner_id, $space] = $this->prepareSpace();

        $result = $service->create(
            $space->id,
            $owner_id,
            'invitado@example.test'
        );

        $stmt = $this->db->prepare(
            'UPDATE `space_invitation`
             SET expires_at = DATE_SUB(NOW(), INTERVAL 1 DAY)
             WHERE id = ?'
        );
        $stmt->execute([$result['invitation']->id]);

        $this->expectException(LogicException::class);

        $service->revoke($result['invitation']->id, $owner_id);
    }

    /**
     * Comprueba que los espacios personales no permiten gestión de invitaciones.
     */
    public function testPersonalSpaceCannotListInvitations(): void {
        $owner_id = $this->createUser();

        $space = new Space();
        $space->name = 'Personal';
        $space->type = 'personal';
        $space->owner_id = $owner_id;
        $space->personal_owner_id = $owner_id;

        $this->assertTrue($space->save());
        $this->assertNotNull($space->id);
        $this->spaces[] = $space->id;

        $service = new SpaceInvitationService();

        $this->expectException(DomainException::class);

        $service->getBySpace($space->id, $owner_id);
    }

    /**
     * Prepara propietario, espacio y servicio.
     *
     * @return array{SpaceInvitationService, int, Space}
     */
    private function prepareSpace(): array {
        $owner_id = $this->createUser();
        $space = (new SpaceService())->createSharedSpace(
            $owner_id,
            'Familia de prueba'
        );

        $this->assertNotNull($space->id);
        $this->spaces[] = $space->id;

        return [
            new SpaceInvitationService(),
            $owner_id,
            $space
        ];
    }

    /**
     * Crea un usuario exclusivo de integración.
     *
     * @return int Identificador del usuario creado.
     */
    private function createUser(): int {
        $stmt = $this->db->prepare(
            'INSERT INTO `user` (`name`, `email`, `password_hash`)
             VALUES (?, ?, ?)'
        );

        $stmt->execute([
            'Usuario de prueba',
            'manage-test-' . bin2hex(random_bytes(12)) . '@example.test',
            password_hash('contraseña segura', PASSWORD_DEFAULT)
        ]);

        $id = (int) $this->db->lastInsertId();
        $this->users[] = $id;

        return $id;
    }
}

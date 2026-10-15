<?php declare(strict_types=1);

require_once __DIR__ . '/OrmIntegrationTestCase.php';

use Osumi\OsumiFramework\App\Model\Space;
use Osumi\OsumiFramework\App\Model\SpaceMember;
use Osumi\OsumiFramework\App\Service\Space\SpaceInvitationService;
use Osumi\OsumiFramework\App\Service\Space\SpaceMemberService;
use Osumi\OsumiFramework\App\Service\Space\SpaceService;

final class SpaceMemberLifecycleIntegrationTest extends OrmIntegrationTestCase {
    /** @var list<int> */
    private array $users = [];

    /** @var list<int> */
    private array $spaces = [];

    /**
     * Limpia los registros de integración.
     */
    protected function tearDown(): void {
        try {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            foreach (array_reverse($this->spaces) as $space_id) {
                foreach (['space_invitation', 'space_member'] as $table) {
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
     * Comprueba que una baja conserva la fila original.
     */
    public function testLeavePreservesMembership(): void {
        [$space, $owner_id, $guest_id] = $this->prepareMembers();
        $before = $this->membership($space->id, $guest_id);

        (new SpaceMemberService())->leave($space->id, $guest_id);

        $after = $this->membership($space->id, $guest_id);

        $this->assertSame($before['id'], $after['id']);
        $this->assertNotNull($after['left_at']);
        $this->assertFalse($this->db->inTransaction());
    }

    /**
     * Comprueba que únicamente el propietario puede expulsar.
     */
    public function testOnlyOwnerCanRemove(): void {
        [$space, $owner_id, $guest_id] = $this->prepareMembers();
        $outsider_id = $this->createUser();

        $service = new SpaceMemberService();

        try {
            $service->remove(
                $space->id,
                $outsider_id,
                $guest_id
            );
            $this->fail('Se ha permitido una expulsión no autorizada.');
        }
        catch (DomainException) {
            $this->assertNull(
                $this->membership($space->id, $guest_id)['left_at']
            );
        }

        $service->remove($space->id, $owner_id, $guest_id);

        $this->assertNotNull(
            $this->membership($space->id, $guest_id)['left_at']
        );
    }

    /**
     * Comprueba que el propietario no puede darse de baja.
     */
    public function testOwnerCannotLeave(): void {
        [$space, $owner_id] = $this->prepareMembers();

        $this->expectException(DomainException::class);

        (new SpaceMemberService())->leave($space->id, $owner_id);
    }

    /**
     * Comprueba que no se permite repetir una baja.
     */
    public function testCannotLeaveTwice(): void {
        [$space, $owner_id, $guest_id] = $this->prepareMembers();
        $service = new SpaceMemberService();

        $service->leave($space->id, $guest_id);

        $this->expectException(LogicException::class);

        $service->leave($space->id, $guest_id);
    }

    /**
     * Comprueba que puede invitarse de nuevo a un miembro inactivo.
     */
    public function testInactiveMemberCanBeInvitedAgain(): void {
        [$space, $owner_id, $guest_id, $guest_email] =
            $this->prepareMembers();

        (new SpaceMemberService())->leave($space->id, $guest_id);

        $result = (new SpaceInvitationService())->create(
            $space->id,
            $owner_id,
            $guest_email
        );

        $this->assertNotNull($result['invitation']->id);
    }

    /**
     * Comprueba que aceptar una nueva invitación reactiva la
     * membresía anterior sin duplicar la fila.
     */
    public function testRejoiningPreservesMembershipId(): void {
        [$space, $owner_id, $guest_id, $guest_email] =
            $this->prepareMembers();

        $before = $this->membership($space->id, $guest_id);

        $members = new SpaceMemberService();
        $members->leave($space->id, $guest_id);

        $invitations = new SpaceInvitationService();
        $result = $invitations->create(
            $space->id,
            $owner_id,
            $guest_email
        );

        $invitations->accept($result['token'], $guest_id);

        $after = $this->membership($space->id, $guest_id);

        $this->assertSame($before['id'], $after['id']);
        $this->assertNull($after['left_at']);

        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM `space_member`
             WHERE id_space = ? AND id_user = ?'
        );
        $stmt->execute([$space->id, $guest_id]);

        $this->assertSame(1, (int) $stmt->fetchColumn());
    }

    /**
     * Prepara un propietario, un espacio y un invitado activo.
     *
     * @return array{Space, int, int, string}
     */
    private function prepareMembers(): array {
        $owner_id = $this->createUser();
        $guest_email = $this->uniqueEmail();
        $guest_id = $this->createUser($guest_email);

        $space = (new SpaceService())->createSharedSpace(
            $owner_id,
            'Espacio integración'
        );

        $this->assertNotNull($space->id);
        $this->spaces[] = $space->id;

        $member = new SpaceMember();
        $member->id_space = $space->id;
        $member->id_user = $guest_id;
        $member->left_at = null;

        $this->assertTrue($member->save());

        return [$space, $owner_id, $guest_id, $guest_email];
    }

    /**
     * Lee una membresía directamente de la base de pruebas.
     *
     * @param int $space_id Espacio.
     * @param int $user_id Usuario.
     * @return array{id: int, left_at: ?string}
     */
    private function membership(int $space_id, int $user_id): array {
        $stmt = $this->db->prepare(
            'SELECT id, left_at FROM `space_member`
             WHERE id_space = ? AND id_user = ?'
        );
        $stmt->execute([$space_id, $user_id]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $this->assertIsArray($row);

        return [
            'id' => (int) $row['id'],
            'left_at' => $row['left_at']
        ];
    }

    /**
     * Crea un usuario de integración.
     *
     * @param string|null $email Correo opcional.
     * @return int Identificador.
     */
    private function createUser(?string $email = null): int {
        $stmt = $this->db->prepare(
            'INSERT INTO `user` (`name`, `email`, `password_hash`)
             VALUES (?, ?, ?)'
        );
        $stmt->execute([
            'Usuario integración',
            $email ?? $this->uniqueEmail(),
            password_hash('contraseña segura', PASSWORD_DEFAULT)
        ]);

        $id = (int) $this->db->lastInsertId();
        $this->users[] = $id;
        return $id;
    }

    /**
     * Genera un correo exclusivo de prueba.
     *
     * @return string Correo.
     */
    private function uniqueEmail(): string {
        return 'member-lifecycle-' .
            bin2hex(random_bytes(12)) . '@example.test';
    }
}

<?php declare(strict_types=1);

require_once dirname(__DIR__) . '/OrmIntegrationTestCase.php';

use Osumi\OsumiFramework\App\Model\SpaceInvitation;
use Osumi\OsumiFramework\App\Service\Space\SpaceInvitationService;
use Osumi\OsumiFramework\App\Service\Space\SpaceService;

final class AcceptSpaceInvitationIntegrationTest extends OrmIntegrationTestCase {
    /** @var list<int> */
    private array $users = [];

    /** @var list<int> */
    private array $spaces = [];

    /**
     * Limpia los registros utilizados por cada prueba.
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
     * Comprueba una aceptación correcta y su persistencia.
     */
    public function testAcceptInvitation(): void {
        [$service, $space_id, $guest_id, $token] =
            $this->prepareInvitation();

        $space = $service->accept($token, $guest_id);

        $this->assertSame($space_id, $space->id);

        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM `space_member`
             WHERE id_space = ? AND id_user = ?'
        );
        $stmt->execute([$space_id, $guest_id]);

        $this->assertSame(1, (int) $stmt->fetchColumn());

        $invitation = SpaceInvitation::findOne([
            'token_hash' => hash('sha256', $token)
        ]);

        $this->assertNotNull($invitation);
        $this->assertNotNull($invitation->accepted_at);
        $this->assertNull($invitation->revoked_at);
        $this->assertFalse($this->db->inTransaction());
    }

    /**
     * Comprueba que el token no puede utilizarse dos veces.
     */
    public function testCannotAcceptTwice(): void {
        [$service, $space_id, $guest_id, $token] =
            $this->prepareInvitation();

        $service->accept($token, $guest_id);

        $this->expectException(LogicException::class);

        $service->accept($token, $guest_id);
    }

    /**
     * Comprueba que otro usuario no puede aceptar una invitación ajena.
     */
    public function testWrongRecipient(): void {
        [$service, $space_id, $guest_id, $token] =
            $this->prepareInvitation();

        $other_id = $this->createUser(
            $this->uniqueEmail()
        );

        try {
            $service->accept($token, $other_id);
            $this->fail('Se ha permitido aceptar una invitación ajena.');
        }
        catch (DomainException) {
            $stmt = $this->db->prepare(
                'SELECT COUNT(*) FROM `space_member`
                 WHERE id_space = ? AND id_user = ?'
            );
            $stmt->execute([$space_id, $other_id]);

            $this->assertSame(0, (int) $stmt->fetchColumn());
        }
    }

    /**
     * Comprueba que no puede aceptarse una invitación caducada.
     */
    public function testExpiredInvitation(): void {
        [$service, $space_id, $guest_id, $token] =
            $this->prepareInvitation();

        $stmt = $this->db->prepare(
            'UPDATE `space_invitation`
             SET expires_at = DATE_SUB(NOW(), INTERVAL 1 DAY)
             WHERE token_hash = ?'
        );
        $stmt->execute([hash('sha256', $token)]);

        $this->expectException(LogicException::class);

        $service->accept($token, $guest_id);
    }

    /**
     * Comprueba que una invitación revocada no puede aceptarse.
     */
    public function testRevokedInvitation(): void {
        [$service, $space_id, $guest_id, $token] =
            $this->prepareInvitation();

        $stmt = $this->db->prepare(
            'UPDATE `space_invitation`
             SET revoked_at = NOW()
             WHERE token_hash = ?'
        );
        $stmt->execute([hash('sha256', $token)]);

        $this->expectException(LogicException::class);

        $service->accept($token, $guest_id);
    }

    /**
     * Comprueba que un token inexistente es rechazado.
     */
    public function testUnknownToken(): void {
        $service = new SpaceInvitationService();

        $this->expectException(LogicException::class);

        $service->accept(str_repeat('a', 64), 123);
    }

    /**
     * Prepara una invitación válida en la base de pruebas.
     *
     * @return array{SpaceInvitationService, int, int, string}
     */
    private function prepareInvitation(): array {
        $owner_id = $this->createUser($this->uniqueEmail());
        $guest_email = $this->uniqueEmail();
        $guest_id = $this->createUser($guest_email);

        $space = (new SpaceService())->createSharedSpace(
            $owner_id,
            'Familia integración'
        );

        $this->assertNotNull($space->id);
        $this->spaces[] = $space->id;

        $service = new SpaceInvitationService();

        $invitation = $service->create(
            $space->id,
            $owner_id,
            $guest_email
        );

        return [
            $service,
            $space->id,
            $guest_id,
            $invitation['token']
        ];
    }

    /**
     * Crea un usuario de prueba.
     *
     * @param string $email Correo electrónico.
     * @return int Identificador generado.
     */
    private function createUser(string $email): int {
        $stmt = $this->db->prepare(
            'INSERT INTO `user` (`name`, `email`, `password_hash`)
             VALUES (?, ?, ?)'
        );

        $stmt->execute([
            'Usuario integración',
            $email,
            password_hash('contraseña segura', PASSWORD_DEFAULT)
        ]);

        $id = (int) $this->db->lastInsertId();
        $this->users[] = $id;

        return $id;
    }

    /**
     * Genera un correo de prueba exclusivo.
     *
     * @return string Correo electrónico.
     */
    private function uniqueEmail(): string {
        return 'accept-test-' .
            bin2hex(random_bytes(12)) .
            '@example.test';
    }
}

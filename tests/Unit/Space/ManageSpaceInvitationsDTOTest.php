<?php declare(strict_types=1);

use Osumi\OsumiFramework\App\DTO\Space\GetSpaceInvitationsDTO;
use Osumi\OsumiFramework\App\DTO\Space\RevokeSpaceInvitationDTO;
use Osumi\OsumiFramework\App\Middleware\LoginMiddleware;
use Osumi\OsumiFramework\App\Service\Auth\AuthService;
use Osumi\OsumiFramework\Core\OCore;
use Osumi\OsumiFramework\Core\OMiddleware;
use Osumi\OsumiFramework\Web\ORequest;
use PHPUnit\Framework\TestCase;

final class ManageSpaceInvitationsDTOTest extends TestCase {
    private bool $core_existed = false;
    private mixed $previous_core = null;

    /**
     * Prepara el entorno aislado de autenticación.
     */
    protected function setUp(): void {
        parent::setUp();

        $this->core_existed = array_key_exists('core', $GLOBALS);
        if ($this->core_existed) {
            $this->previous_core = $GLOBALS['core'];
        }

        $GLOBALS['core'] = new OCore();

        OMiddleware::reset();
        OMiddleware::setRoute([
            OMiddleware::PHASE_BEFORE => [
                LoginMiddleware::class
            ]
        ]);
    }

    /**
     * Restaura el entorno anterior.
     */
    protected function tearDown(): void {
        OMiddleware::reset();

        if ($this->core_existed) {
            $GLOBALS['core'] = $this->previous_core;
        }
        else {
            unset($GLOBALS['core']);
        }

        parent::tearDown();
    }

    /**
     * Comprueba que el listado usa el usuario del middleware.
     */
    public function testListUsesAuthenticatedUser(): void {
        $this->authenticateAs(10);

        $dto = new GetSpaceInvitationsDTO(
            $this->request('GET', [
                'spaceId' => 25,
                'idUser' => 999
            ])
        );

        $this->assertTrue($dto->isValid());
        $this->assertSame(25, $dto->spaceId);
        $this->assertSame(10, $dto->idUser);
    }

    /**
     * Comprueba que la revocación usa el usuario del middleware.
     */
    public function testRevokeUsesAuthenticatedUser(): void {
        $this->authenticateAs(10);

        $dto = new RevokeSpaceInvitationDTO(
            $this->request('POST', [
                'invitationId' => 25,
                'idUser' => 999
            ])
        );

        $this->assertTrue($dto->isValid());
        $this->assertSame(25, $dto->invitationId);
        $this->assertSame(10, $dto->idUser);
    }

    /**
     * Comprueba que un identificador de cliente no sustituye la autenticación.
     */
    public function testAuthenticationIsRequired(): void {
        $dto = new RevokeSpaceInvitationDTO(
            $this->request('POST', [
                'invitationId' => 25,
                'idUser' => 999
            ])
        );

        $this->assertFalse($dto->isValid());
        $this->assertNull($dto->idUser);
    }

    /**
     * Comprueba los campos obligatorios.
     */
    public function testRequiredIds(): void {
        $this->authenticateAs(10);

        $list = new GetSpaceInvitationsDTO(
            $this->request('GET', [])
        );
        $revoke = new RevokeSpaceInvitationDTO(
            $this->request('POST', [])
        );

        $this->assertFalse($list->isValid());
        $this->assertFalse($revoke->isValid());
    }

    /**
     * Publica una identidad autenticada mediante LoginMiddleware.
     *
     * @param int $id Identificador del usuario.
     */
    private function authenticateAs(int $id): void {
        $service = $this->createMock(AuthService::class);

        $service->expects($this->once())
            ->method('authenticateToken')
            ->with('token-prueba')
            ->willReturn([
                'id' => $id,
                'name' => 'Usuario',
                'email' => 'usuario@example.test'
            ]);

        $GLOBALS['core']->services['Auth'] = $service;

        $result = OMiddleware::runPhase(
            OMiddleware::PHASE_BEFORE,
            [
                'headers' => [
                    'Authorization' => 'Bearer token-prueba'
                ]
            ]
        );

        $this->assertFalse($result['stop']);
    }

    /**
     * Construye una petición para el DTO.
     *
     * @param string $method Método HTTP.
     * @param array<string, mixed> $params Parámetros.
     * @return ORequest Petición.
     */
    private function request(string $method, array $params): ORequest {
        return new ORequest([
            'method' => $method,
            'headers' => [],
            'params' => $params
        ]);
    }
}

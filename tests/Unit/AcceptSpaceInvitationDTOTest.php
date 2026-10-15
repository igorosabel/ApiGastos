<?php declare(strict_types=1);

use Osumi\OsumiFramework\App\DTO\AcceptSpaceInvitationDTO;
use Osumi\OsumiFramework\App\Middleware\LoginMiddleware;
use Osumi\OsumiFramework\App\Service\Auth\AuthService;
use Osumi\OsumiFramework\Core\OCore;
use Osumi\OsumiFramework\Core\OMiddleware;
use Osumi\OsumiFramework\Web\ORequest;
use PHPUnit\Framework\TestCase;

final class AcceptSpaceInvitationDTOTest extends TestCase {
    private bool $core_existed = false;
    private mixed $previous_core = null;

    /**
     * Prepara el entorno del middleware.
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
     * Comprueba que no puede suplantarse al usuario autenticado.
     */
    public function testAuthenticatedIdentityCannotBeOverridden(): void {
        $auth = $this->createMock(AuthService::class);
        $auth->expects($this->once())
            ->method('authenticateToken')
            ->with('token-prueba')
            ->willReturn([
                'id' => 10,
                'name' => 'Invitado',
                'email' => 'invitado@example.test'
            ]);

        $GLOBALS['core']->services['Auth'] = $auth;

        $result = OMiddleware::runPhase(
            OMiddleware::PHASE_BEFORE,
            ['headers' => ['Authorization' => 'Bearer token-prueba']]
        );

        $this->assertFalse($result['stop']);

        $dto = $this->createDto([
            'token' => str_repeat('a', 64),
            'idUser' => 999
        ]);

        $this->assertTrue($dto->isValid());
        $this->assertSame(10, $dto->idUser);
    }

    /**
     * Comprueba que el DTO no acepta una identidad suministrada
     * únicamente por el cliente.
     */
    public function testAuthenticationIsRequired(): void {
        $dto = $this->createDto([
            'token' => str_repeat('a', 64),
            'idUser' => 999
        ]);

        $this->assertFalse($dto->isValid());
        $this->assertNull($dto->idUser);
    }

    /**
     * Comprueba que el token es obligatorio.
     */
    public function testTokenIsRequired(): void {
        $dto = $this->createDto([]);

        $this->assertFalse($dto->isValid());
        $this->assertNull($dto->token);
    }

    /**
     * Construye un DTO con los parámetros indicados.
     *
     * @param array<string, mixed> $params Parámetros de petición.
     * @return AcceptSpaceInvitationDTO DTO resultante.
     */
    private function createDto(array $params): AcceptSpaceInvitationDTO {
        return new AcceptSpaceInvitationDTO(
            new ORequest([
                'method' => 'POST',
                'headers' => [],
                'params' => $params
            ])
        );
    }
}

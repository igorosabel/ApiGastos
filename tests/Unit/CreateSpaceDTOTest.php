<?php declare(strict_types=1);

use Osumi\OsumiFramework\App\DTO\CreateSpaceDTO;
use Osumi\OsumiFramework\App\Middleware\LoginMiddleware;
use Osumi\OsumiFramework\App\Service\Auth\AuthService;
use Osumi\OsumiFramework\Core\OCore;
use Osumi\OsumiFramework\Core\OMiddleware;
use Osumi\OsumiFramework\Web\ORequest;
use PHPUnit\Framework\TestCase;

final class CreateSpaceDTOTest extends TestCase {
    private bool $core_existed = false;
    private mixed $previous_core = null;

    /**
     * Prepara el contexto aislado de OFW.
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
     * Restaura el estado global anterior.
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
     * Comprueba que el identificador procede del middleware.
     */
    public function testAuthenticatedUserIsLoadedFromMiddleware(): void {
        $this->authenticateAs(10);

        $dto = $this->createDto([
            'name' => 'Familia'
        ]);

        $this->assertTrue($dto->isValid());
        $this->assertSame('Familia', $dto->name);
        $this->assertSame(10, $dto->idUser);
    }

    /**
     * Comprueba que el cliente no puede suplantar otro usuario.
     */
    public function testClientCannotOverrideAuthenticatedUser(): void {
        $this->authenticateAs(10);

        $dto = $this->createDto([
            'name' => 'Familia',
            'idUser' => 999
        ]);

        $this->assertTrue($dto->isValid());
        $this->assertSame(10, $dto->idUser);
    }

    /**
     * Comprueba que el DTO rechaza la ausencia de autenticación.
     */
    public function testClientUserIdWithoutAuthenticationIsRejected(): void {
        $dto = $this->createDto([
            'name' => 'Familia',
            'idUser' => 999
        ]);

        $this->assertFalse($dto->isValid());
        $this->assertNull($dto->idUser);
    }

    /**
     * Comprueba que el nombre del espacio es obligatorio.
     */
    public function testMissingNameIsRejected(): void {
        $this->authenticateAs(10);

        $dto = $this->createDto([]);

        $this->assertFalse($dto->isValid());
        $this->assertNull($dto->name);
        $this->assertSame(10, $dto->idUser);
    }

    /**
     * Publica un contexto autenticado mediante el middleware real.
     *
     * @param int $user_id Identificador autenticado.
     */
    private function authenticateAs(int $user_id): void {
        $service = $this->createMock(AuthService::class);

        $service->expects($this->once())
            ->method('authenticateToken')
            ->with('token-de-prueba')
            ->willReturn([
                'id' => $user_id,
                'name' => 'Usuario de prueba',
                'email' => 'usuario@example.test'
            ]);

        $GLOBALS['core']->services['Auth'] = $service;

        $result = OMiddleware::runPhase(
            OMiddleware::PHASE_BEFORE,
            [
                'headers' => [
                    'Authorization' => 'Bearer token-de-prueba'
                ]
            ]
        );

        $this->assertFalse($result['stop']);
    }

    /**
     * Construye un DTO con los parámetros suministrados.
     *
     * @param array<string, mixed> $params Parámetros del cliente.
     * @return CreateSpaceDTO DTO construido.
     */
    private function createDto(array $params): CreateSpaceDTO {
        return new CreateSpaceDTO(
            new ORequest([
                'method' => 'POST',
                'headers' => [],
                'params' => $params
            ])
        );
    }
}

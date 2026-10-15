<?php declare(strict_types=1);

use Osumi\OsumiFramework\App\DTO\Space\CreateSpaceInvitationDTO;
use Osumi\OsumiFramework\App\Middleware\LoginMiddleware;
use Osumi\OsumiFramework\App\Service\Auth\AuthService;
use Osumi\OsumiFramework\Core\OCore;
use Osumi\OsumiFramework\Core\OMiddleware;
use Osumi\OsumiFramework\Web\ORequest;
use PHPUnit\Framework\TestCase;

final class CreateSpaceInvitationDTOTest extends TestCase {
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
     * Restaura el contexto anterior de OFW.
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
     * Comprueba la carga de los datos válidos.
     */
    public function testValidInvitationData(): void {
        $this->authenticateAs(10);

        $dto = $this->createDto([
            'spaceId' => 25,
            'email' => 'invitado@example.com'
        ]);

        $this->assertTrue($dto->isValid());
        $this->assertSame(25, $dto->spaceId);
        $this->assertSame('invitado@example.com', $dto->email);
        $this->assertSame(10, $dto->idUser);
    }

    /**
     * Comprueba que no se puede suplantar al usuario autenticado.
     */
    public function testCannotOverrideAuthenticatedUser(): void {
        $this->authenticateAs(10);

        $dto = $this->createDto([
            'spaceId' => 25,
            'email' => 'invitado@example.com',
            'idUser' => 999
        ]);

        $this->assertTrue($dto->isValid());
        $this->assertSame(10, $dto->idUser);
    }

    /**
     * Comprueba el rechazo de una petición sin autenticación.
     */
    public function testMissingAuthentication(): void {
        $dto = $this->createDto([
            'spaceId' => 25,
            'email' => 'invitado@example.com',
            'idUser' => 999
        ]);

        $this->assertFalse($dto->isValid());
        $this->assertNull($dto->idUser);
    }

    /**
     * Comprueba que el identificador del espacio es obligatorio.
     */
    public function testMissingSpaceId(): void {
        $this->authenticateAs(10);

        $dto = $this->createDto([
            'email' => 'invitado@example.com'
        ]);

        $this->assertFalse($dto->isValid());
        $this->assertNull($dto->spaceId);
    }

    /**
     * Comprueba que el correo es obligatorio.
     */
    public function testMissingEmail(): void {
        $this->authenticateAs(10);

        $dto = $this->createDto([
            'spaceId' => 25
        ]);

        $this->assertFalse($dto->isValid());
        $this->assertNull($dto->email);
    }

    /**
     * Publica el contexto de autenticación mediante LoginMiddleware.
     *
     * @param int $user_id Identificador del usuario.
     * @return void
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
     * Construye el DTO utilizando los parámetros indicados.
     *
     * @param array<string, mixed> $params Parámetros del cliente.
     * @return CreateSpaceInvitationDTO DTO resultante.
     */
    private function createDto(array $params): CreateSpaceInvitationDTO {
        return new CreateSpaceInvitationDTO(
            new ORequest([
                'method' => 'POST',
                'headers' => [],
                'params' => $params
            ])
        );
    }
}

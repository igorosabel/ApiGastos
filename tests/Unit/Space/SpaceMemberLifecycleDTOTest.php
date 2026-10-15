<?php declare(strict_types=1);

use Osumi\OsumiFramework\App\DTO\Space\LeaveSpaceDTO;
use Osumi\OsumiFramework\App\DTO\Space\RemoveSpaceMemberDTO;
use Osumi\OsumiFramework\App\Middleware\LoginMiddleware;
use Osumi\OsumiFramework\App\Service\Auth\AuthService;
use Osumi\OsumiFramework\Core\OCore;
use Osumi\OsumiFramework\Core\OMiddleware;
use Osumi\OsumiFramework\Web\ORequest;
use PHPUnit\Framework\TestCase;

final class SpaceMemberLifecycleDTOTest extends TestCase {
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
     * Restaura el entorno anterior de OFW.
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
     * Comprueba los datos válidos de una salida voluntaria.
     */
    public function testLeaveWithAuthenticatedUser(): void {
        $this->authenticateAs(10);

        $dto = new LeaveSpaceDTO(
            $this->request([
                'spaceId' => 25
            ])
        );

        $this->assertTrue($dto->isValid());
        $this->assertSame(25, $dto->spaceId);
        $this->assertSame(10, $dto->idUser);
    }

    /**
     * Comprueba que el cliente no puede falsificar su identidad
     * al abandonar un espacio.
     */
    public function testLeaveCannotOverrideAuthenticatedUser(): void {
        $this->authenticateAs(10);

        $dto = new LeaveSpaceDTO(
            $this->request([
                'spaceId' => 25,
                'idUser' => 999
            ])
        );

        $this->assertTrue($dto->isValid());
        $this->assertSame(25, $dto->spaceId);
        $this->assertSame(10, $dto->idUser);
    }

    /**
     * Comprueba los datos válidos de una expulsión.
     */
    public function testRemoveWithAuthenticatedUser(): void {
        $this->authenticateAs(10);

        $dto = new RemoveSpaceMemberDTO(
            $this->request([
                'spaceId' => 25,
                'userId' => 30
            ])
        );

        $this->assertTrue($dto->isValid());
        $this->assertSame(25, $dto->spaceId);
        $this->assertSame(30, $dto->userId);
        $this->assertSame(10, $dto->idUser);
    }

    /**
     * Comprueba que userId identifica al miembro expulsado
     * mientras idUser procede del middleware.
     */
    public function testRemoveCannotOverrideAuthenticatedUser(): void {
        $this->authenticateAs(10);

        $dto = new RemoveSpaceMemberDTO(
            $this->request([
                'spaceId' => 25,
                'userId' => 30,
                'idUser' => 999
            ])
        );

        $this->assertTrue($dto->isValid());
        $this->assertSame(25, $dto->spaceId);
        $this->assertSame(30, $dto->userId);
        $this->assertSame(10, $dto->idUser);
    }

    /**
     * Comprueba que el espacio es obligatorio para salir.
     */
    public function testLeaveRequiresSpaceId(): void {
        $this->authenticateAs(10);

        $dto = new LeaveSpaceDTO(
            $this->request([])
        );

        $this->assertFalse($dto->isValid());
        $this->assertNull($dto->spaceId);
        $this->assertSame(10, $dto->idUser);
    }

    /**
     * Comprueba que el espacio es obligatorio para expulsar.
     */
    public function testRemoveRequiresSpaceId(): void {
        $this->authenticateAs(10);

        $dto = new RemoveSpaceMemberDTO(
            $this->request([
                'userId' => 30
            ])
        );

        $this->assertFalse($dto->isValid());
        $this->assertNull($dto->spaceId);
        $this->assertSame(30, $dto->userId);
    }

    /**
     * Comprueba que el miembro afectado es obligatorio.
     */
    public function testRemoveRequiresUserId(): void {
        $this->authenticateAs(10);

        $dto = new RemoveSpaceMemberDTO(
            $this->request([
                'spaceId' => 25
            ])
        );

        $this->assertFalse($dto->isValid());
        $this->assertSame(25, $dto->spaceId);
        $this->assertNull($dto->userId);
    }

    /**
     * Comprueba que no se permite abandonar un espacio
     * utilizando una identidad enviada por el cliente.
     */
    public function testLeaveRequiresAuthentication(): void {
        $dto = new LeaveSpaceDTO(
            $this->request([
                'spaceId' => 25,
                'idUser' => 999
            ])
        );

        $this->assertFalse($dto->isValid());
        $this->assertNull($dto->idUser);
    }

    /**
     * Comprueba que no se permite expulsar miembros
     * utilizando una identidad enviada por el cliente.
     */
    public function testRemoveRequiresAuthentication(): void {
        $dto = new RemoveSpaceMemberDTO(
            $this->request([
                'spaceId' => 25,
                'userId' => 30,
                'idUser' => 999
            ])
        );

        $this->assertFalse($dto->isValid());
        $this->assertNull($dto->idUser);
    }

    /**
     * Publica una identidad autenticada mediante LoginMiddleware.
     *
     * @param int $user_id Identificador del usuario autenticado.
     * @return void
     */
    private function authenticateAs(int $user_id): void {
        $service = $this->createMock(AuthService::class);

        $service->expects($this->once())
            ->method('authenticateToken')
            ->with('token-prueba')
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
                    'Authorization' => 'Bearer token-prueba'
                ]
            ]
        );

        $this->assertFalse($result['stop']);
    }

    /**
     * Construye una petición POST con los parámetros indicados.
     *
     * @param array<string, mixed> $params Parámetros del cliente.
     * @return ORequest Petición construida.
     */
    private function request(array $params): ORequest {
        return new ORequest([
            'method' => 'POST',
            'headers' => [],
            'params' => $params
        ]);
    }
}

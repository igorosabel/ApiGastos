<?php declare(strict_types=1);

use Osumi\OsumiFramework\App\DTO\Transaction\DeleteTransactionDTO;
use Osumi\OsumiFramework\App\Middleware\LoginMiddleware;
use Osumi\OsumiFramework\App\Service\Auth\AuthService;
use Osumi\OsumiFramework\Core\OCore;
use Osumi\OsumiFramework\Core\OMiddleware;
use Osumi\OsumiFramework\Web\ORequest;
use PHPUnit\Framework\TestCase;

final class DeleteTransactionDTOTest extends TestCase {
    private bool $core_existed = false;
    private mixed $previous_core = null;

    /**
     * Prepara el contexto aislado de autenticación.
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
     * Restaura el contexto original.
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
     * Comprueba los datos de una eliminación válida.
     */
    public function testValidDelete(): void {
        $this->authenticateAs(10);

        $dto = $this->createDto([
            'spaceId' => 25,
            'transactionId' => 48
        ]);

        $this->assertTrue($dto->isValid());
        $this->assertSame(25, $dto->spaceId);
        $this->assertSame(48, $dto->transactionId);
        $this->assertSame(10, $dto->idUser);
    }

    /**
     * Comprueba que no puede falsificarse el solicitante.
     */
    public function testCannotOverrideAuthenticatedUser(): void {
        $this->authenticateAs(10);

        $dto = $this->createDto([
            'spaceId' => 25,
            'transactionId' => 48,
            'idUser' => 999
        ]);

        $this->assertTrue($dto->isValid());
        $this->assertSame(10, $dto->idUser);
    }

    /**
     * Comprueba que el espacio es obligatorio.
     */
    public function testRequiresSpaceId(): void {
        $this->authenticateAs(10);

        $dto = $this->createDto([
            'transactionId' => 48
        ]);

        $this->assertFalse($dto->isValid());
        $this->assertNull($dto->spaceId);
    }

    /**
     * Comprueba que el movimiento es obligatorio.
     */
    public function testRequiresTransactionId(): void {
        $this->authenticateAs(10);

        $dto = $this->createDto([
            'spaceId' => 25
        ]);

        $this->assertFalse($dto->isValid());
        $this->assertNull($dto->transactionId);
    }

    /**
     * Comprueba que la autenticación es obligatoria.
     */
    public function testRequiresAuthentication(): void {
        $dto = $this->createDto([
            'spaceId' => 25,
            'transactionId' => 48,
            'idUser' => 999
        ]);

        $this->assertFalse($dto->isValid());
        $this->assertNull($dto->idUser);
    }

    /**
     * Publica una identidad mediante LoginMiddleware.
     *
     * @param int $id Identificador autenticado.
     * @return void
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
     * Construye el DTO desde una petición POST.
     *
     * @param array<string, mixed> $params Datos recibidos.
     * @return DeleteTransactionDTO DTO.
     */
    private function createDto(array $params): DeleteTransactionDTO {
        return new DeleteTransactionDTO(
            new ORequest([
                'method' => 'POST',
                'headers' => [],
                'params' => $params
            ])
        );
    }
}

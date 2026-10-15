<?php declare(strict_types=1);

use Osumi\OsumiFramework\App\DTO\Recurring\GetOccurrencesDTO;
use Osumi\OsumiFramework\App\DTO\Recurring\ResolveOccurrenceDTO;
use Osumi\OsumiFramework\App\Middleware\LoginMiddleware;
use Osumi\OsumiFramework\App\Service\Auth\AuthService;
use Osumi\OsumiFramework\Core\OCore;
use Osumi\OsumiFramework\Core\OMiddleware;
use Osumi\OsumiFramework\Web\ORequest;
use PHPUnit\Framework\TestCase;

final class OccurrenceManagementDTOTest extends TestCase {
    private bool $core_existed = false;
    private mixed $previous_core = null;

    /**
     * Prepara el middleware de autenticación.
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
     * Restaura el contexto de OFW.
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
     * Comprueba la consulta con filtro opcional.
     */
    public function testValidListWithStatus(): void {
        $this->authenticateAs(10);

        $dto = new GetOccurrencesDTO(
            $this->request('GET', [
                'spaceId' => 25,
                'status' => 'pending',
                'idUser' => 999
            ])
        );

        $this->assertTrue($dto->isValid());
        $this->assertSame(25, $dto->spaceId);
        $this->assertSame('pending', $dto->status);
        $this->assertSame(10, $dto->idUser);
    }

    /**
     * Comprueba la ausencia válida del filtro.
     */
    public function testListWithoutStatus(): void {
        $this->authenticateAs(10);

        $dto = new GetOccurrencesDTO(
            $this->request('GET', ['spaceId' => 25])
        );

        $this->assertTrue($dto->isValid());
        $this->assertNull($dto->status);
    }

    /**
     * Comprueba una confirmación válida.
     */
    public function testValidConfirm(): void {
        $this->authenticateAs(10);

        $dto = new ResolveOccurrenceDTO(
            $this->request('POST', [
                'spaceId' => 25,
                'occurrenceId' => 60,
                'action' => 'confirm',
                'idUser' => 999
            ])
        );

        $this->assertTrue($dto->isValid());
        $this->assertSame(25, $dto->spaceId);
        $this->assertSame(60, $dto->occurrenceId);
        $this->assertSame('confirm', $dto->action);
        $this->assertSame(10, $dto->idUser);
    }

    /**
     * Comprueba que omitir también es una petición válida.
     */
    public function testValidSkip(): void {
        $this->authenticateAs(10);

        $dto = new ResolveOccurrenceDTO(
            $this->request('POST', [
                'spaceId' => 25,
                'occurrenceId' => 60,
                'action' => 'skip'
            ])
        );

        $this->assertTrue($dto->isValid());
        $this->assertSame('skip', $dto->action);
    }

    /**
     * Comprueba los campos obligatorios para resolver.
     */
    public function testResolveRequiredFields(): void {
        $this->authenticateAs(10);

        $params = [
            'spaceId' => 25,
            'occurrenceId' => 60,
            'action' => 'confirm'
        ];

        foreach (array_keys($params) as $missing) {
            $candidate = $params;
            unset($candidate[$missing]);

            $dto = new ResolveOccurrenceDTO(
                $this->request('POST', $candidate)
            );

            $this->assertFalse(
                $dto->isValid(),
                "Debe exigirse {$missing}."
            );
        }
    }

    /**
     * Comprueba que la consulta requiere espacio.
     */
    public function testListRequiresSpace(): void {
        $this->authenticateAs(10);

        $dto = new GetOccurrencesDTO(
            $this->request('GET', ['status' => 'pending'])
        );

        $this->assertFalse($dto->isValid());
    }

    /**
     * Comprueba que la identidad autenticada es obligatoria.
     */
    public function testAuthenticationRequired(): void {
        $list = new GetOccurrencesDTO(
            $this->request('GET', [
                'spaceId' => 25,
                'idUser' => 999
            ])
        );

        $resolve = new ResolveOccurrenceDTO(
            $this->request('POST', [
                'spaceId' => 25,
                'occurrenceId' => 60,
                'action' => 'confirm',
                'idUser' => 999
            ])
        );

        $this->assertFalse($list->isValid());
        $this->assertFalse($resolve->isValid());
        $this->assertNull($list->idUser);
        $this->assertNull($resolve->idUser);
    }

    /**
     * Publica una identidad autenticada.
     *
     * @param int $id Usuario.
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
     * Construye una petición.
     *
     * @param string $method Método HTTP.
     * @param array<string, mixed> $params Parámetros.
     * @return ORequest Petición.
     */
    private function request(
        string $method,
        array $params
    ): ORequest {
        return new ORequest([
            'method' => $method,
            'headers' => [],
            'params' => $params
        ]);
    }
}

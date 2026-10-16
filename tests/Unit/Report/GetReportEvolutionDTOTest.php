<?php declare(strict_types=1);

use Osumi\OsumiFramework\App\DTO\Report\GetReportEvolutionDTO;
use Osumi\OsumiFramework\App\Middleware\LoginMiddleware;
use Osumi\OsumiFramework\App\Service\Auth\AuthService;
use Osumi\OsumiFramework\Core\OCore;
use Osumi\OsumiFramework\Core\OMiddleware;
use Osumi\OsumiFramework\Web\ORequest;
use PHPUnit\Framework\TestCase;

final class GetReportEvolutionDTOTest extends TestCase {
    private bool $core_existed = false;
    private mixed $previous_core = null;

    /**
     * Prepara OFW y el middleware de autenticación.
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
     * Restaura el contexto anterior.
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
     * Comprueba una petición válida y evita la suplantación.
     */
    public function testValidEvolution(): void {
        $this->authenticateAs(10);

        $dto = $this->createDto([
            'spaceId' => 25,
            'monthFrom' => '2026-01',
            'monthTo' => '2026-12',
            'idUser' => 999
        ]);

        $this->assertTrue($dto->isValid());
        $this->assertSame(25, $dto->spaceId);
        $this->assertSame('2026-01', $dto->monthFrom);
        $this->assertSame('2026-12', $dto->monthTo);
        $this->assertSame(10, $dto->idUser);
    }

    /**
     * Comprueba todos los campos obligatorios.
     */
    public function testRequiredFields(): void {
        $this->authenticateAs(10);

        foreach (['spaceId', 'monthFrom', 'monthTo'] as $missing) {
            $params = [
                'spaceId' => 25,
                'monthFrom' => '2026-01',
                'monthTo' => '2026-12'
            ];
            unset($params[$missing]);

            $dto = $this->createDto($params);

            $this->assertFalse(
                $dto->isValid(),
                "El campo {$missing} debería ser obligatorio."
            );
        }
    }

    /**
     * Comprueba que la autenticación es obligatoria.
     */
    public function testAuthenticationRequired(): void {
        $dto = $this->createDto([
            'spaceId' => 25,
            'monthFrom' => '2026-01',
            'monthTo' => '2026-12',
            'idUser' => 999
        ]);

        $this->assertFalse($dto->isValid());
        $this->assertNull($dto->idUser);
    }

    /**
     * Publica la identidad del usuario autenticado.
     *
     * @param int $id Identificador.
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
     * Construye un DTO desde la petición GET.
     *
     * @param array<string, mixed> $params Parámetros.
     * @return GetReportEvolutionDTO DTO.
     */
    private function createDto(array $params): GetReportEvolutionDTO {
        return new GetReportEvolutionDTO(
            new ORequest([
                'method' => 'GET',
                'headers' => [],
                'params' => $params
            ])
        );
    }
}

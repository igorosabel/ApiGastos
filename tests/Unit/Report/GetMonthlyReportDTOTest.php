<?php declare(strict_types=1);

use Osumi\OsumiFramework\App\DTO\Report\GetMonthlyReportDTO;
use Osumi\OsumiFramework\App\Middleware\LoginMiddleware;
use Osumi\OsumiFramework\App\Service\Auth\AuthService;
use Osumi\OsumiFramework\Core\OCore;
use Osumi\OsumiFramework\Core\OMiddleware;
use Osumi\OsumiFramework\Web\ORequest;
use PHPUnit\Framework\TestCase;

final class GetMonthlyReportDTOTest extends TestCase {
    private bool $core_existed = false;
    private mixed $previous_core = null;

    /**
     * Prepara el contexto OFW.
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
     * Restaura el contexto OFW.
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
     * Comprueba la petición y la identidad autenticada.
     */
    public function testValidMonthlyReport(): void {
        $this->authenticateAs(10);

        $dto = $this->createDto([
            'spaceId' => 25,
            'month' => '2026-10',
            'idUser' => 999
        ]);

        $this->assertTrue($dto->isValid());
        $this->assertSame(25, $dto->spaceId);
        $this->assertSame('2026-10', $dto->month);
        $this->assertSame(10, $dto->idUser);
    }

    /**
     * Comprueba los parámetros obligatorios.
     */
    public function testRequiredFields(): void {
        $this->authenticateAs(10);

        foreach (['spaceId', 'month'] as $missing) {
            $params = [
                'spaceId' => 25,
                'month' => '2026-10'
            ];
            unset($params[$missing]);

            $dto = $this->createDto($params);
            $this->assertFalse($dto->isValid());
        }
    }

    /**
     * Rechaza peticiones sin identidad autenticada.
     */
    public function testRequiresAuthentication(): void {
        $dto = $this->createDto([
            'spaceId' => 25,
            'month' => '2026-10',
            'idUser' => 999
        ]);

        $this->assertFalse($dto->isValid());
        $this->assertNull($dto->idUser);
    }

    /**
     * Autentica un usuario de prueba mediante middleware.
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
     * Construye la petición de consulta.
     *
     * @param array<string,mixed> $params Parámetros.
     * @return GetMonthlyReportDTO DTO.
     */
    private function createDto(array $params): GetMonthlyReportDTO {
        return new GetMonthlyReportDTO(
            new ORequest([
                'method' => 'GET',
                'headers' => [],
                'params' => $params
            ])
        );
    }
}

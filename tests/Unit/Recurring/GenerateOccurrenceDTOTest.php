<?php declare(strict_types=1);

use Osumi\OsumiFramework\App\DTO\Recurring\GenerateOccurrenceDTO;
use Osumi\OsumiFramework\App\Middleware\LoginMiddleware;
use Osumi\OsumiFramework\App\Service\Auth\AuthService;
use Osumi\OsumiFramework\Core\OCore;
use Osumi\OsumiFramework\Core\OMiddleware;
use Osumi\OsumiFramework\Web\ORequest;
use PHPUnit\Framework\TestCase;

final class GenerateOccurrenceDTOTest extends TestCase {
    private bool $core_existed = false;
    private mixed $previous_core = null;

    /**
     * Prepara el entorno de autenticación.
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
     * Comprueba una petición válida y el usuario autenticado.
     */
    public function testValidGenerate(): void {
        $this->authenticateAs(10);

        $dto = $this->createDto([
            'spaceId' => 25,
            'recurringId' => 40,
            'period' => '2026-11-01'
        ]);

        $this->assertTrue($dto->isValid());
        $this->assertSame(25, $dto->spaceId);
        $this->assertSame(40, $dto->recurringId);
        $this->assertSame('2026-11-01', $dto->period);
        $this->assertSame(10, $dto->idUser);
    }

    /**
     * Comprueba que el cliente no puede suplantar al usuario.
     */
    public function testCannotOverrideAuthenticatedUser(): void {
        $this->authenticateAs(10);

        $dto = $this->createDto([
            'spaceId' => 25,
            'recurringId' => 40,
            'period' => '2026-11-01',
            'idUser' => 999
        ]);

        $this->assertTrue($dto->isValid());
        $this->assertSame(10, $dto->idUser);
    }

    /**
     * Comprueba todos los campos obligatorios.
     */
    public function testRequiredFields(): void {
        $this->authenticateAs(10);

        $params = [
            'spaceId' => 25,
            'recurringId' => 40,
            'period' => '2026-11-01'
        ];

        foreach (array_keys($params) as $missing) {
            $candidate = $params;
            unset($candidate[$missing]);

            $dto = $this->createDto($candidate);

            $this->assertFalse(
                $dto->isValid(),
                "Debe exigirse el campo {$missing}."
            );
        }
    }

    /**
     * Comprueba que la autenticación es obligatoria.
     */
    public function testAuthenticationRequired(): void {
        $dto = $this->createDto([
            'spaceId' => 25,
            'recurringId' => 40,
            'period' => '2026-11-01',
            'idUser' => 999
        ]);

        $this->assertFalse($dto->isValid());
        $this->assertNull($dto->idUser);
    }

    /**
     * Autentica al usuario mediante LoginMiddleware.
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
     * Construye la petición de generación.
     *
     * @param array<string, mixed> $params Datos.
     * @return GenerateOccurrenceDTO DTO.
     */
    private function createDto(array $params): GenerateOccurrenceDTO {
        return new GenerateOccurrenceDTO(
            new ORequest([
                'method' => 'POST',
                'headers' => [],
                'params' => $params
            ])
        );
    }
}

<?php declare(strict_types=1);

use Osumi\OsumiFramework\App\DTO\Recurring\UpdateRecurringDTO;
use Osumi\OsumiFramework\App\DTO\Recurring\SetRecurringStatusDTO;
use Osumi\OsumiFramework\App\Middleware\LoginMiddleware;
use Osumi\OsumiFramework\App\Service\Auth\AuthService;
use Osumi\OsumiFramework\Core\OCore;
use Osumi\OsumiFramework\Core\OMiddleware;
use Osumi\OsumiFramework\Web\ORequest;
use PHPUnit\Framework\TestCase;

final class ManageRecurringDTOTest extends TestCase {
    private bool $core_existed = false;
    private mixed $previous_core = null;

    /**
     * Prepara el contexto de OFW.
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
     * Comprueba una edición válida.
     */
    public function testValidUpdate(): void {
        $this->authenticateAs(10);

        $dto = new UpdateRecurringDTO($this->request([
            'spaceId' => 25,
            'recurringId' => 40,
            'categoryId' => 30,
            'type' => 'expense',
            'amount' => '900.00',
            'concept' => 'Alquiler',
            'dayOfMonth' => 31,
            'startDate' => '2026-11-01',
            'endDate' => null,
            'idUser' => 999
        ]));

        $this->assertTrue($dto->isValid());
        $this->assertSame(25, $dto->spaceId);
        $this->assertSame(40, $dto->recurringId);
        $this->assertSame(30, $dto->categoryId);
        $this->assertSame('expense', $dto->type);
        $this->assertSame('900.00', $dto->amount);
        $this->assertSame('Alquiler', $dto->concept);
        $this->assertSame(31, $dto->dayOfMonth);
        $this->assertSame('2026-11-01', $dto->startDate);
        $this->assertNull($dto->endDate);
        $this->assertSame(10, $dto->idUser);
    }

    /**
     * Comprueba los campos obligatorios de edición.
     */
    public function testRequiredUpdateFields(): void {
        $this->authenticateAs(10);

        $params = [
            'spaceId' => 25,
            'recurringId' => 40,
            'categoryId' => 30,
            'type' => 'expense',
            'amount' => '900.00',
            'concept' => 'Alquiler',
            'dayOfMonth' => 31,
            'startDate' => '2026-11-01'
        ];

        foreach (array_keys($params) as $missing) {
            $candidate = $params;
            unset($candidate[$missing]);

            $dto = new UpdateRecurringDTO(
                $this->request($candidate)
            );

            $this->assertFalse(
                $dto->isValid(),
                "El campo {$missing} debería ser obligatorio."
            );
        }
    }

    /**
     * Comprueba que la fecha final puede omitirse.
     */
    public function testOptionalEndDate(): void {
        $this->authenticateAs(10);

        $dto = new UpdateRecurringDTO($this->request([
            'spaceId' => 25,
            'recurringId' => 40,
            'categoryId' => 30,
            'type' => 'expense',
            'amount' => '900.00',
            'concept' => 'Alquiler',
            'dayOfMonth' => 31,
            'startDate' => '2026-11-01'
        ]));

        $this->assertTrue($dto->isValid());
        $this->assertNull($dto->endDate);
    }

    /**
     * Comprueba que false es un estado válido.
     */
    public function testDeactivateWithFalse(): void {
        $this->authenticateAs(10);

        $dto = new SetRecurringStatusDTO($this->request([
            'spaceId' => 25,
            'recurringId' => 40,
            'active' => false,
            'idUser' => 999
        ]));

        $this->assertTrue($dto->isValid());
        $this->assertFalse($dto->active);
        $this->assertSame(10, $dto->idUser);
    }

    /**
     * Comprueba que true es un estado válido.
     */
    public function testActivateWithTrue(): void {
        $this->authenticateAs(10);

        $dto = new SetRecurringStatusDTO($this->request([
            'spaceId' => 25,
            'recurringId' => 40,
            'active' => true
        ]));

        $this->assertTrue($dto->isValid());
        $this->assertTrue($dto->active);
    }

    /**
     * Comprueba que el estado es obligatorio.
     */
    public function testStatusIsRequired(): void {
        $this->authenticateAs(10);

        $dto = new SetRecurringStatusDTO($this->request([
            'spaceId' => 25,
            'recurringId' => 40
        ]));

        $this->assertFalse($dto->isValid());
        $this->assertNull($dto->active);
    }

    /**
     * Comprueba que los identificadores son obligatorios.
     */
    public function testStatusRequiresIdentifiers(): void {
        $this->authenticateAs(10);

        foreach (['spaceId', 'recurringId'] as $missing) {
            $params = [
                'spaceId' => 25,
                'recurringId' => 40,
                'active' => false
            ];
            unset($params[$missing]);

            $dto = new SetRecurringStatusDTO(
                $this->request($params)
            );

            $this->assertFalse($dto->isValid());
        }
    }

    /**
     * Comprueba la autenticación obligatoria de ambos DTO.
     */
    public function testAuthenticationIsRequired(): void {
        $update = new UpdateRecurringDTO($this->request([
            'spaceId' => 25,
            'recurringId' => 40,
            'categoryId' => 30,
            'type' => 'expense',
            'amount' => '900.00',
            'concept' => 'Alquiler',
            'dayOfMonth' => 31,
            'startDate' => '2026-11-01',
            'idUser' => 999
        ]));

        $status = new SetRecurringStatusDTO($this->request([
            'spaceId' => 25,
            'recurringId' => 40,
            'active' => false,
            'idUser' => 999
        ]));

        $this->assertFalse($update->isValid());
        $this->assertFalse($status->isValid());
        $this->assertNull($update->idUser);
        $this->assertNull($status->idUser);
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
     * Crea una petición HTTP de prueba.
     *
     * @param array<string, mixed> $params Parámetros.
     * @return ORequest Petición.
     */
    private function request(array $params): ORequest {
        return new ORequest([
            'method' => 'POST',
            'headers' => [],
            'params' => $params
        ]);
    }
}

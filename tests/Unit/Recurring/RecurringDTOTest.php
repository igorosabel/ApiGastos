<?php declare(strict_types=1);

use Osumi\OsumiFramework\App\DTO\Recurring\CreateRecurringDTO;
use Osumi\OsumiFramework\App\DTO\Recurring\GetRecurringDTO;
use Osumi\OsumiFramework\App\Middleware\LoginMiddleware;
use Osumi\OsumiFramework\App\Service\Auth\AuthService;
use Osumi\OsumiFramework\Core\OCore;
use Osumi\OsumiFramework\Core\OMiddleware;
use Osumi\OsumiFramework\Web\ORequest;
use PHPUnit\Framework\TestCase;

final class RecurringDTOTest extends TestCase {
    private bool $core_existed = false;
    private mixed $previous_core = null;

    /**
     * Configura el contexto del middleware.
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
     * Comprueba una plantilla válida.
     */
    public function testValidCreate(): void {
        $this->authenticateAs(10);

        $dto = new CreateRecurringDTO(
            $this->request('POST', [
                'spaceId' => 25,
                'categoryId' => 30,
                'type' => 'expense',
                'amount' => '850.00',
                'concept' => 'Alquiler',
                'dayOfMonth' => 31,
                'startDate' => '2026-11-01',
                'endDate' => null
            ])
        );

        $this->assertTrue($dto->isValid());
        $this->assertSame(25, $dto->spaceId);
        $this->assertSame(30, $dto->categoryId);
        $this->assertSame('850.00', $dto->amount);
        $this->assertSame(31, $dto->dayOfMonth);
        $this->assertNull($dto->endDate);
        $this->assertSame(10, $dto->idUser);
    }

    /**
     * Comprueba que endDate es opcional.
     */
    public function testOptionalEndDate(): void {
        $this->authenticateAs(10);

        $dto = new CreateRecurringDTO(
            $this->request('POST', [
                'spaceId' => 25,
                'categoryId' => 30,
                'type' => 'income',
                'amount' => '100.00',
                'concept' => 'Ingreso mensual',
                'dayOfMonth' => 1,
                'startDate' => '2026-11-01'
            ])
        );

        $this->assertTrue($dto->isValid());
        $this->assertNull($dto->endDate);
    }

    /**
     * Comprueba los campos obligatorios.
     */
    public function testRequiredFields(): void {
        $this->authenticateAs(10);

        $fields = [
            'spaceId',
            'categoryId',
            'type',
            'amount',
            'concept',
            'dayOfMonth',
            'startDate'
        ];

        foreach ($fields as $missing) {
            $params = [
                'spaceId' => 25,
                'categoryId' => 30,
                'type' => 'expense',
                'amount' => '10.00',
                'concept' => 'Prueba',
                'dayOfMonth' => 15,
                'startDate' => '2026-11-01'
            ];

            unset($params[$missing]);

            $dto = new CreateRecurringDTO(
                $this->request('POST', $params)
            );

            $this->assertFalse(
                $dto->isValid(),
                "Debería faltar {$missing}."
            );
        }
    }

    /**
     * Comprueba que el usuario no puede suplantarse.
     */
    public function testCannotOverrideUser(): void {
        $this->authenticateAs(10);

        $dto = new CreateRecurringDTO(
            $this->request('POST', [
                'spaceId' => 25,
                'categoryId' => 30,
                'type' => 'expense',
                'amount' => '10.00',
                'concept' => 'Prueba',
                'dayOfMonth' => 15,
                'startDate' => '2026-11-01',
                'idUser' => 999
            ])
        );

        $this->assertTrue($dto->isValid());
        $this->assertSame(10, $dto->idUser);
    }

    /**
     * Comprueba la consulta válida de plantillas.
     */
    public function testValidList(): void {
        $this->authenticateAs(10);

        $dto = new GetRecurringDTO(
            $this->request('GET', [
                'spaceId' => 25
            ])
        );

        $this->assertTrue($dto->isValid());
        $this->assertSame(25, $dto->spaceId);
        $this->assertSame(10, $dto->idUser);
    }

    /**
     * Comprueba la autenticación de creación.
     */
    public function testCreateRequiresAuthentication(): void {
        $dto = new CreateRecurringDTO(
            $this->request('POST', [
                'spaceId' => 25,
                'categoryId' => 30,
                'type' => 'expense',
                'amount' => '10.00',
                'concept' => 'Prueba',
                'dayOfMonth' => 15,
                'startDate' => '2026-11-01',
                'idUser' => 999
            ])
        );

        $this->assertFalse($dto->isValid());
        $this->assertNull($dto->idUser);
    }

    /**
     * Comprueba la autenticación del listado.
     */
    public function testListRequiresAuthentication(): void {
        $dto = new GetRecurringDTO(
            $this->request('GET', [
                'spaceId' => 25,
                'idUser' => 999
            ])
        );

        $this->assertFalse($dto->isValid());
        $this->assertNull($dto->idUser);
    }

    /**
     * Publica una identidad mediante el middleware.
     *
     * @param int $id Usuario autenticado.
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
     * Construye una petición de OFW.
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

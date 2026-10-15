<?php declare(strict_types=1);

use Osumi\OsumiFramework\App\DTO\Transaction\CreateTransactionDTO;
use Osumi\OsumiFramework\App\DTO\Transaction\GetTransactionsDTO;
use Osumi\OsumiFramework\App\Middleware\LoginMiddleware;
use Osumi\OsumiFramework\App\Service\Auth\AuthService;
use Osumi\OsumiFramework\Core\OCore;
use Osumi\OsumiFramework\Core\OMiddleware;
use Osumi\OsumiFramework\Web\ORequest;
use PHPUnit\Framework\TestCase;

final class TransactionDTOTest extends TestCase {
    private bool $core_existed = false;
    private mixed $previous_core = null;

    /**
     * Prepara el middleware para cada prueba.
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
     * Comprueba los datos de creación de un movimiento.
     */
    public function testValidCreate(): void {
        $this->authenticateAs(10);

        $dto = new CreateTransactionDTO(
            $this->request('POST', [
                'spaceId' => 25,
                'categoryId' => 30,
                'type' => 'expense',
                'amount' => '49.95',
                'concept' => 'Compra semanal',
                'date' => '2026-10-15'
            ])
        );

        $this->assertTrue($dto->isValid());
        $this->assertSame(25, $dto->spaceId);
        $this->assertSame(30, $dto->categoryId);
        $this->assertSame('expense', $dto->type);
        $this->assertSame('49.95', $dto->amount);
        $this->assertSame('Compra semanal', $dto->concept);
        $this->assertSame('2026-10-15', $dto->date);
        $this->assertSame(10, $dto->idUser);
    }

    /**
     * Comprueba que el concepto puede omitirse.
     */
    public function testOptionalConcept(): void {
        $this->authenticateAs(10);

        $dto = new CreateTransactionDTO(
            $this->request('POST', [
                'spaceId' => 25,
                'categoryId' => 30,
                'type' => 'income',
                'amount' => '150.00',
                'date' => '2026-10-15'
            ])
        );

        $this->assertTrue($dto->isValid());
        $this->assertNull($dto->concept);
    }

    /**
     * Comprueba que el autor siempre procede del JWT.
     */
    public function testCannotOverrideAuthenticatedUser(): void {
        $this->authenticateAs(10);

        $dto = new CreateTransactionDTO(
            $this->request('POST', [
                'spaceId' => 25,
                'categoryId' => 30,
                'type' => 'expense',
                'amount' => '49.95',
                'date' => '2026-10-15',
                'idUser' => 999
            ])
        );

        $this->assertTrue($dto->isValid());
        $this->assertSame(10, $dto->idUser);
    }

    /**
     * Comprueba los campos obligatorios de creación.
     */
    public function testRequiredCreateFields(): void {
        $this->authenticateAs(10);

        foreach ([
            'spaceId',
            'categoryId',
            'type',
            'amount',
            'date'
        ] as $missing) {
            $params = [
                'spaceId' => 25,
                'categoryId' => 30,
                'type' => 'expense',
                'amount' => '49.95',
                'date' => '2026-10-15'
            ];

            unset($params[$missing]);

            $dto = new CreateTransactionDTO(
                $this->request('POST', $params)
            );

            $this->assertFalse(
                $dto->isValid(),
                "Debería faltar el campo {$missing}."
            );
        }
    }

    /**
     * Comprueba el listado autenticado.
     */
    public function testValidList(): void {
        $this->authenticateAs(10);

        $dto = new GetTransactionsDTO(
            $this->request('GET', [
                'spaceId' => 25,
                'idUser' => 999
            ])
        );

        $this->assertTrue($dto->isValid());
        $this->assertSame(25, $dto->spaceId);
        $this->assertSame(10, $dto->idUser);
    }

    /**
     * Comprueba que no se permite crear sin autenticación.
     */
    public function testCreateRequiresAuthentication(): void {
        $dto = new CreateTransactionDTO(
            $this->request('POST', [
                'spaceId' => 25,
                'categoryId' => 30,
                'type' => 'expense',
                'amount' => '49.95',
                'date' => '2026-10-15',
                'idUser' => 999
            ])
        );

        $this->assertFalse($dto->isValid());
        $this->assertNull($dto->idUser);
    }

    /**
     * Comprueba que no se permite listar sin autenticación.
     */
    public function testListRequiresAuthentication(): void {
        $dto = new GetTransactionsDTO(
            $this->request('GET', [
                'spaceId' => 25,
                'idUser' => 999
            ])
        );

        $this->assertFalse($dto->isValid());
        $this->assertNull($dto->idUser);
    }

    /**
     * Publica un usuario autenticado mediante el middleware.
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

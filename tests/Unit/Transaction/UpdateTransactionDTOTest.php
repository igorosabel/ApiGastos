<?php declare(strict_types=1);

use Osumi\OsumiFramework\App\DTO\Transaction\UpdateTransactionDTO;
use Osumi\OsumiFramework\App\Middleware\LoginMiddleware;
use Osumi\OsumiFramework\App\Service\Auth\AuthService;
use Osumi\OsumiFramework\Core\OCore;
use Osumi\OsumiFramework\Core\OMiddleware;
use Osumi\OsumiFramework\Web\ORequest;
use PHPUnit\Framework\TestCase;

final class UpdateTransactionDTOTest extends TestCase {
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
     * Comprueba todos los campos de una edición válida.
     */
    public function testValidUpdate(): void {
        $this->authenticateAs(10);

        $dto = $this->createDto([
            'spaceId' => 25,
            'transactionId' => 48,
            'categoryId' => 30,
            'type' => 'expense',
            'amount' => '54.90',
            'concept' => 'Compra corregida',
            'date' => '2026-10-15'
        ]);

        $this->assertTrue($dto->isValid());
        $this->assertSame(25, $dto->spaceId);
        $this->assertSame(48, $dto->transactionId);
        $this->assertSame(30, $dto->categoryId);
        $this->assertSame('expense', $dto->type);
        $this->assertSame('54.90', $dto->amount);
        $this->assertSame('Compra corregida', $dto->concept);
        $this->assertSame('2026-10-15', $dto->date);
        $this->assertSame(10, $dto->idUser);
    }

    /**
     * Comprueba que el concepto es opcional.
     */
    public function testOptionalConcept(): void {
        $this->authenticateAs(10);

        $dto = $this->createDto([
            'spaceId' => 25,
            'transactionId' => 48,
            'categoryId' => 30,
            'type' => 'income',
            'amount' => '100.00',
            'date' => '2026-10-15'
        ]);

        $this->assertTrue($dto->isValid());
        $this->assertNull($dto->concept);
    }

    /**
     * Comprueba que la identidad procede del middleware
     * incluso cuando el cliente envía otro identificador.
     */
    public function testCannotOverrideAuthenticatedUser(): void {
        $this->authenticateAs(10);

        $dto = $this->createDto([
            'spaceId' => 25,
            'transactionId' => 48,
            'categoryId' => 30,
            'type' => 'expense',
            'amount' => '54.90',
            'date' => '2026-10-15',
            'idUser' => 999
        ]);

        $this->assertTrue($dto->isValid());
        $this->assertSame(10, $dto->idUser);
    }

    /**
     * Comprueba los campos obligatorios.
     */
    public function testRequiredFields(): void {
        $this->authenticateAs(10);

        $required = [
            'spaceId',
            'transactionId',
            'categoryId',
            'type',
            'amount',
            'date'
        ];

        foreach ($required as $missing) {
            $params = [
                'spaceId' => 25,
                'transactionId' => 48,
                'categoryId' => 30,
                'type' => 'expense',
                'amount' => '54.90',
                'date' => '2026-10-15'
            ];

            unset($params[$missing]);

            $dto = $this->createDto($params);

            $this->assertFalse(
                $dto->isValid(),
                "Debería faltar el campo {$missing}."
            );
        }
    }

    /**
     * Comprueba que no puede editarse sin autenticación.
     */
    public function testAuthenticationRequired(): void {
        $dto = $this->createDto([
            'spaceId' => 25,
            'transactionId' => 48,
            'categoryId' => 30,
            'type' => 'expense',
            'amount' => '54.90',
            'date' => '2026-10-15',
            'idUser' => 999
        ]);

        $this->assertFalse($dto->isValid());
        $this->assertNull($dto->idUser);
    }

    /**
     * Publica la identidad autenticada.
     *
     * @param int $id Identificador del usuario.
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
     * Construye el DTO a partir de una petición POST.
     *
     * @param array<string, mixed> $params Datos de entrada.
     * @return UpdateTransactionDTO DTO resultante.
     */
    private function createDto(array $params): UpdateTransactionDTO {
        return new UpdateTransactionDTO(
            new ORequest([
                'method' => 'POST',
                'headers' => [],
                'params' => $params
            ])
        );
    }
}

<?php declare(strict_types=1);

use Osumi\OsumiFramework\App\Middleware\LoginMiddleware;
use Osumi\OsumiFramework\App\Service\Auth\AuthService;
use Osumi\OsumiFramework\Core\OCore;
use Osumi\OsumiFramework\Core\OMiddleware;
use PHPUnit\Framework\TestCase;

final class LoginMiddlewareTest extends TestCase {
    private bool $core_existed = false;
    private mixed $previous_core = null;

    /**
     * Prepara un núcleo aislado para los tests.
     */
    protected function setUp(): void {
        parent::setUp();

        $this->core_existed = array_key_exists('core', $GLOBALS);

        if ($this->core_existed) {
            $this->previous_core = $GLOBALS['core'];
        }

        $GLOBALS['core'] = new OCore();
        OMiddleware::reset();
    }

    /**
     * Restaura el estado de OFW.
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
     * Comprueba el rechazo de peticiones sin token.
     */
    public function testMissingToken(): void {
        $result = LoginMiddleware::handle(
            OMiddleware::PHASE_BEFORE,
            ['headers' => []]
        );

        $this->assertTrue($result['stop']);
        $this->assertSame(401, $result['status_code']);
    }

    /**
     * Comprueba el rechazo de tokens con formato incorrecto.
     */
    public function testMalformedAuthorizationHeader(): void {
        $result = LoginMiddleware::handle(
            OMiddleware::PHASE_BEFORE,
            ['headers' => ['Authorization' => 'Token incorrecto']]
        );

        $this->assertTrue($result['stop']);
        $this->assertSame(401, $result['status_code']);
    }

    /**
     * Comprueba que un JWT rechazado impide continuar.
     */
    public function testInvalidToken(): void {
        $service = $this->createMock(AuthService::class);
        $service->expects($this->once())
            ->method('authenticateToken')
            ->with('token-invalido')
            ->willReturn(null);

        $GLOBALS['core']->services['Auth'] = $service;

        $result = LoginMiddleware::handle(
            OMiddleware::PHASE_BEFORE,
            ['headers' => ['Authorization' => 'Bearer token-invalido']]
        );

        $this->assertTrue($result['stop']);
        $this->assertSame(401, $result['status_code']);
    }

    /**
     * Comprueba que la identidad validada se publica en el contexto.
     */
    public function testValidToken(): void {
        $service = $this->createMock(AuthService::class);
        $service->expects($this->once())
            ->method('authenticateToken')
            ->with('token-valido')
            ->willReturn([
                'id' => 10,
                'name' => 'Usuario',
                'email' => 'test@example.com'
            ]);

        $GLOBALS['core']->services['Auth'] = $service;

        $result = LoginMiddleware::handle(
            OMiddleware::PHASE_BEFORE,
            ['headers' => ['authorization' => 'Bearer token-valido']]
        );

        $this->assertSame([
            'context' => [
                'id' => 10,
                'name' => 'Usuario',
                'email' => 'test@example.com'
            ]
        ], $result);
    }

    /**
     * Comprueba que el middleware no interviene fuera de before.
     */
    public function testOtherPhase(): void {
        $this->assertSame(
            [],
            LoginMiddleware::handle(
                OMiddleware::PHASE_AFTER_RESPONSE,
                []
            )
        );
    }
}

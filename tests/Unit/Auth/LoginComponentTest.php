<?php declare(strict_types=1);

use Osumi\OsumiFramework\App\Module\Auth\Login\LoginComponent;
use Osumi\OsumiFramework\App\Service\Auth\AuthService;
use Osumi\OsumiFramework\Core\OMiddleware;
use Osumi\OsumiFramework\Web\ORequest;
use PHPUnit\Framework\TestCase;

final class LoginComponentTest extends TestCase {
    /**
     * Reinicia el estado HTTP antes de cada prueba.
     */
    protected function setUp(): void {
        parent::setUp();
        OMiddleware::reset();
    }

    /**
     * Limpia el estado HTTP después de cada prueba.
     */
    protected function tearDown(): void {
        OMiddleware::reset();
        parent::tearDown();
    }

    /**
     * Comprueba el rechazo de una petición incompleta.
     */
    public function testMissingCredentialsReturns400(): void {
        $service = $this->createMock(AuthService::class);
        $service->expects($this->never())->method('login');

        $component = $this->createComponent($service);
        $component->run($this->createRequest([]));

        $this->assertSame(400, OMiddleware::getStatusCode());
        $this->assertFalse($component->success);
        $this->assertSame('validation_error', $component->error);
    }

    /**
     * Comprueba que las credenciales incorrectas devuelven 401.
     */
    public function testInvalidCredentialsReturns401(): void {
        $service = $this->createMock(AuthService::class);
        $service->expects($this->once())
            ->method('login')
            ->with('test@example.com', 'incorrecta')
            ->willReturn(null);

        $component = $this->createComponent($service);
        $component->run($this->createRequest([
            'email' => 'test@example.com',
            'password' => 'incorrecta'
        ]));

        $this->assertSame(401, OMiddleware::getStatusCode());
        $this->assertFalse($component->success);
        $this->assertSame('invalid_credentials', $component->error);
        $this->assertNull($component->token);
    }

    /**
     * Comprueba que un login válido devuelve el token.
     */
    public function testSuccessfulLoginReturns200(): void {
        $service = $this->createMock(AuthService::class);
        $expires_at = time() + 86400;

        $service->expects($this->once())
            ->method('login')
            ->with('test@example.com', 'contraseña segura')
            ->willReturn([
                'token' => 'token-de-prueba',
                'expires_at' => $expires_at,
                'user_id' => 12,
                'name' => 'Usuario',
                'email' => 'test@example.com'
            ]);

        $component = $this->createComponent($service);
        $component->run($this->createRequest([
            'email' => 'test@example.com',
            'password' => 'contraseña segura'
        ]));

        $this->assertSame(200, OMiddleware::getStatusCode());
        $this->assertTrue($component->success);
        $this->assertSame('token-de-prueba', $component->token);
        $this->assertSame($expires_at, $component->expires_at);
        $this->assertSame(12, $component->user_id);
        $this->assertNull($component->error);
    }

    /**
     * Construye el componente con un servicio simulado.
     *
     * @param AuthService $service Servicio de autenticación.
     * @return LoginComponent Componente preparado.
     */
    private function createComponent(AuthService $service): LoginComponent {
        $reflection = new ReflectionClass(LoginComponent::class);
        $component = $reflection->newInstanceWithoutConstructor();

        $property = new ReflectionProperty(
            LoginComponent::class,
            'auth_service'
        );
        $property->setValue($component, $service);

        return $component;
    }

    /**
     * Construye una petición HTTP de prueba.
     *
     * @param array<string, mixed> $params Parámetros de entrada.
     * @return ORequest Petición simulada.
     */
    private function createRequest(array $params): ORequest {
        return new ORequest([
            'method' => 'POST',
            'headers' => [],
            'params' => $params
        ]);
    }
}

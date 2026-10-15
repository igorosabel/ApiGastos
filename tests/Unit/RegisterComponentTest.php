<?php declare(strict_types=1);

use Osumi\OsumiFramework\App\Module\Auth\Register\RegisterComponent;
use Osumi\OsumiFramework\App\Service\Auth\RegistrationService;
use Osumi\OsumiFramework\Core\OMiddleware;
use Osumi\OsumiFramework\Web\ORequest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RegisterComponentTest extends TestCase {
    /**
     * Reinicia el estado HTTP compartido entre pruebas.
     */
    protected function setUp(): void {
        parent::setUp();
        OMiddleware::reset();
    }

    /**
     * Limpia el estado HTTP al finalizar cada prueba.
     */
    protected function tearDown(): void {
        OMiddleware::reset();
        parent::tearDown();
    }

    /**
     * Comprueba que los datos inválidos producen un error HTTP 400.
     *
     * @param array<string, mixed> $params Datos de entrada.
     */
    #[DataProvider('invalidRequests')]
    public function testInvalidRegistrationReturns400(
        array $params
    ): void {
        $db = $this->createMock(PDO::class);

        $db->expects($this->never())
            ->method('beginTransaction');

        $component = $this->createComponent($db);
        $component->run($this->createRequest($params));

        $this->assertSame(400, OMiddleware::getStatusCode());
        $this->assertFalse($component->success);
        $this->assertNull($component->user_id);
        $this->assertNull($component->space_id);
        $this->assertSame('validation_error', $component->error);
        $this->assertNotEmpty($component->message);
    }

    /**
     * Comprueba la respuesta del componente tras un registro correcto.
     */
    public function testSuccessfulRegistrationReturns201(): void {
        $db = $this->createMock(PDO::class);

        $user = $this->createStub(PDOStatement::class);
        $space = $this->createStub(PDOStatement::class);
        $member = $this->createStub(PDOStatement::class);

        $user->method('execute')->willReturn(true);
        $space->method('execute')->willReturn(true);
        $member->method('execute')->willReturn(true);

        $db->method('prepare')
            ->willReturnOnConsecutiveCalls($user, $space, $member);

        $db->method('lastInsertId')
            ->willReturnOnConsecutiveCalls('10', '20');

        $db->expects($this->once())
            ->method('beginTransaction')
            ->willReturn(true);

        $db->expects($this->once())
            ->method('commit')
            ->willReturn(true);

        $component = $this->createComponent($db);

        $component->run($this->createRequest([
            'name' => 'Usuario',
            'email' => 'test@example.com',
            'password' => 'una contraseña segura'
        ]));

        $this->assertSame(201, OMiddleware::getStatusCode());
        $this->assertTrue($component->success);
        $this->assertSame(10, $component->user_id);
        $this->assertSame(20, $component->space_id);
        $this->assertNull($component->error);
        $this->assertNull($component->message);
    }

    /**
     * Comprueba que un correo duplicado devuelve HTTP 409.
     */
    public function testDuplicateEmailReturns409(): void {
        $db = $this->createMock(PDO::class);
        $stmt = $this->createStub(PDOStatement::class);

        $error = new PDOException('Duplicate entry', 23000);
        $error->errorInfo = [
            '23000',
            1062,
            "Duplicate entry 'test@example.com' for key 'uq_user_email'"
        ];

        $stmt->method('execute')->willThrowException($error);

        $db->method('prepare')->willReturn($stmt);
        $db->method('beginTransaction')->willReturn(true);
        $db->method('inTransaction')->willReturn(true);

        $db->expects($this->once())
            ->method('rollBack')
            ->willReturn(true);

        $component = $this->createComponent($db);

        $component->run($this->createRequest([
            'name' => 'Usuario',
            'email' => 'test@example.com',
            'password' => 'una contraseña segura'
        ]));

        $this->assertSame(409, OMiddleware::getStatusCode());
        $this->assertFalse($component->success);
        $this->assertNull($component->user_id);
        $this->assertNull($component->space_id);
        $this->assertSame(
            'email_already_registered',
            $component->error
        );
        $this->assertNotEmpty($component->message);
    }

    /**
     * Construye el componente con un servicio controlado para la prueba.
     *
     * @param PDO $db Conexión simulada.
     * @return RegisterComponent Componente preparado.
     */
    private function createComponent(PDO $db): RegisterComponent {
        $reflection = new ReflectionClass(RegisterComponent::class);
        $component = $reflection->newInstanceWithoutConstructor();

        $property = new ReflectionProperty(
            RegisterComponent::class,
            'registration_service'
        );

        $property->setValue(
            $component,
            new RegistrationService($db)
        );

        return $component;
    }

    /**
     * Construye una petición HTTP con los parámetros indicados.
     *
     * @param array<string, mixed> $params Datos de entrada.
     * @return ORequest Petición simulada.
     */
    private function createRequest(array $params): ORequest {
        return new ORequest([
            'method' => 'POST',
            'headers' => [],
            'params' => $params
        ]);
    }

    /**
     * Proporciona solicitudes de registro inválidas.
     *
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function invalidRequests(): iterable {
        yield 'sin datos' => [[]];

        yield 'nombre vacío' => [[
            'name' => '',
            'email' => 'test@example.com',
            'password' => 'contraseña segura'
        ]];

        yield 'correo inválido' => [[
            'name' => 'Usuario',
            'email' => 'correo-invalido',
            'password' => 'contraseña segura'
        ]];

        yield 'contraseña corta' => [[
            'name' => 'Usuario',
            'email' => 'test@example.com',
            'password' => '123'
        ]];

        yield 'contraseña con tipo incorrecto' => [[
            'name' => 'Usuario',
            'email' => 'test@example.com',
            'password' => ['123456789012']
        ]];
    }
}

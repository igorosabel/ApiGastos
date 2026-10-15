<?php declare(strict_types=1);

use Osumi\OsumiFramework\App\Service\Auth\AuthService;
use Osumi\OsumiFramework\Plugins\OToken;
use PHPUnit\Framework\TestCase;

final class AuthServiceTest extends TestCase {
    /**
     * Comprueba la emisión del token con credenciales válidas.
     */
    public function testSuccessfulLogin(): void {
        $db = $this->createStub(PDO::class);
        $stmt = $this->createStub(PDOStatement::class);

        $db->method('prepare')->willReturn($stmt);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetch')->willReturn([
            'id' => 12,
            'name' => 'Usuario',
            'email' => 'test@example.com',
            'password_hash' => password_hash(
                'contraseña segura',
                PASSWORD_DEFAULT
            )
        ]);

        $secret = str_repeat('a', 64);
        $service = new AuthService($db, $secret);

        $result = $service->login(
            '  TEST@EXAMPLE.COM  ',
            'contraseña segura'
        );

        $this->assertIsArray($result);
        $this->assertSame(12, $result['user_id']);
        $this->assertSame('Usuario', $result['name']);
        $this->assertSame('test@example.com', $result['email']);
        $this->assertGreaterThan(time(), $result['expires_at']);

        $token = new OToken($secret);
        $this->assertTrue($token->checkToken($result['token']));
        $this->assertSame(12, $token->getParam('id'));
        $this->assertSame(
            $result['expires_at'],
            $token->getEXP()
        );
    }

    /**
     * Comprueba que una contraseña incorrecta no genera un token.
     */
    public function testInvalidPassword(): void {
        $db = $this->createStub(PDO::class);
        $stmt = $this->createStub(PDOStatement::class);

        $db->method('prepare')->willReturn($stmt);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetch')->willReturn([
            'id' => 12,
            'name' => 'Usuario',
            'email' => 'test@example.com',
            'password_hash' => password_hash(
                'contraseña correcta',
                PASSWORD_DEFAULT
            )
        ]);

        $service = new AuthService($db, str_repeat('a', 64));

        $this->assertNull(
            $service->login('test@example.com', 'contraseña incorrecta')
        );
    }

    /**
     * Comprueba el rechazo de usuarios inexistentes.
     */
    public function testUnknownUser(): void {
        $db = $this->createStub(PDO::class);
        $stmt = $this->createStub(PDOStatement::class);

        $db->method('prepare')->willReturn($stmt);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetch')->willReturn(false);

        $service = new AuthService($db, str_repeat('a', 64));

        $this->assertNull(
            $service->login('desconocido@example.com', 'contraseña segura')
        );
    }
}

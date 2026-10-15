<?php declare(strict_types=1);

use Osumi\OsumiFramework\App\Service\Auth\AuthService;
use Osumi\OsumiFramework\Plugins\OToken;
use PHPUnit\Framework\TestCase;

final class AuthTokenTest extends TestCase {
    /**
     * Comprueba que un token válido identifica a un usuario existente.
     */
    public function testValidToken(): void {
        $db = $this->createStub(PDO::class);
        $stmt = $this->createStub(PDOStatement::class);
        $db->method('prepare')->willReturn($stmt);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetch')->willReturn([
            'id' => 10,
            'name' => 'Usuario',
            'email' => 'test@example.com'
        ]);

        $secret = str_repeat('a', 64);
        $token = new OToken($secret);
        $token->addParam('id', 10);
        $token->setEXP(time() + 3600);

        $service = new AuthService($db, $secret);

        $this->assertSame([
            'id' => 10,
            'name' => 'Usuario',
            'email' => 'test@example.com'
        ], $service->authenticateToken($token->getToken()));
    }

    /**
     * Comprueba que un JWT caducado es rechazado.
     */
    public function testExpiredToken(): void {
        $secret = str_repeat('a', 64);
        $token = new OToken($secret);
        $token->addParam('id', 10);
        $token->setEXP(time() - 3600);

        $db = $this->createMock(PDO::class);
        $db->expects($this->never())->method('prepare');

        $service = new AuthService($db, $secret);

        $this->assertNull(
            $service->authenticateToken($token->getToken())
        );
    }

    /**
     * Comprueba que se rechazan tokens malformados y sin expiración.
     */
    public function testMalformedAndNonExpiringTokens(): void {
        $secret = str_repeat('a', 64);
        $db = $this->createMock(PDO::class);
        $db->expects($this->never())->method('prepare');
        $service = new AuthService($db, $secret);

        $this->assertNull($service->authenticateToken('invalido'));
        $this->assertNull($service->authenticateToken('a.b.c'));

        $token = new OToken($secret);
        $token->addParam('id', 10);

        $this->assertNull(
            $service->authenticateToken($token->getToken())
        );
    }

    /**
     * Comprueba que no se aceptan JWT firmados con otra clave.
     */
    public function testInvalidSignature(): void {
        $token = new OToken(str_repeat('b', 64));
        $token->addParam('id', 10);
        $token->setEXP(time() + 3600);

        $db = $this->createMock(PDO::class);
        $db->expects($this->never())->method('prepare');

        $service = new AuthService($db, str_repeat('a', 64));

        $this->assertNull(
            $service->authenticateToken($token->getToken())
        );
    }

    /**
     * Comprueba que un token válido de un usuario inexistente no autentica.
     */
    public function testDeletedUser(): void {
        $secret = str_repeat('a', 64);
        $token = new OToken($secret);
        $token->addParam('id', 10);
        $token->setEXP(time() + 3600);

        $db = $this->createStub(PDO::class);
        $stmt = $this->createStub(PDOStatement::class);
        $db->method('prepare')->willReturn($stmt);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetch')->willReturn(false);

        $service = new AuthService($db, $secret);

        $this->assertNull(
            $service->authenticateToken($token->getToken())
        );
    }
}

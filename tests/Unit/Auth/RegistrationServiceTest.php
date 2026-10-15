<?php declare(strict_types=1);

use Osumi\OsumiFramework\App\Service\Auth\RegistrationService;
use Osumi\OsumiFramework\App\Service\Auth\EmailAlreadyRegisteredException;
use PHPUnit\Framework\TestCase;

final class RegistrationServiceTest extends TestCase {
    /**
     * Comprueba que se crean usuario, espacio y membresía.
     */
    public function testSuccessfulRegistration(): void {
        $db = $this->createMock(PDO::class);
        $user = $this->createMock(PDOStatement::class);
        $space = $this->createMock(PDOStatement::class);
        $member = $this->createMock(PDOStatement::class);

        $db->expects($this->once())
            ->method('beginTransaction')
            ->willReturn(true);

        $db->expects($this->once())
            ->method('commit')
            ->willReturn(true);

        $db->expects($this->never())
            ->method('rollBack');

        $db->method('prepare')
            ->willReturnOnConsecutiveCalls(
                $user,
                $space,
                $member
            );

        $db->method('lastInsertId')
            ->willReturnOnConsecutiveCalls('10', '20');

        $user->expects($this->once())
            ->method('execute')
            ->willReturnCallback(function (array $params): bool {
                $this->assertSame('Iñigo', $params['name']);
                $this->assertSame(
                    'test@example.com',
                    $params['email']
                );
                $this->assertTrue(password_verify(
                    'una contraseña larga',
                    $params['password_hash']
                ));
                return true;
            });

        $space->expects($this->once())
            ->method('execute')
            ->with([
                'name' => 'Personal',
                'type' => 'personal',
                'owner_id' => 10,
                'personal_owner_id' => 10
            ])
            ->willReturn(true);

        $member->expects($this->once())
            ->method('execute')
            ->with([
                'id_space' => 20,
                'id_user' => 10
            ])
            ->willReturn(true);

        $service = new RegistrationService($db);

        $result = $service->register(
            'Iñigo',
            'test@example.com',
            'una contraseña larga'
        );

        $this->assertSame([
            'user_id' => 10,
            'space_id' => 20
        ], $result);
    }

    /**
     * Comprueba que se revierte la transacción si falla el espacio.
     */
    public function testRollbackWhenSpaceCreationFails(): void {
        $db = $this->createMock(PDO::class);
        $user = $this->createStub(PDOStatement::class);
        $space = $this->createStub(PDOStatement::class);

        $db->method('beginTransaction')->willReturn(true);
        $db->method('inTransaction')->willReturn(true);
        $db->method('lastInsertId')->willReturn('10');

        $db->method('prepare')
            ->willReturnOnConsecutiveCalls($user, $space);

        $user->method('execute')->willReturn(true);

        $space->method('execute')
            ->willThrowException(
                new PDOException('Error simulado')
            );

        $db->expects($this->once())
            ->method('rollBack')
            ->willReturn(true);

        $db->expects($this->never())
            ->method('commit');

        $service = new RegistrationService($db);

        $this->expectException(PDOException::class);

        $service->register(
            'Iñigo',
            'test@example.com',
            'una contraseña larga'
        );
    }

    /**
     * Comprueba que un correo duplicado genera una excepción de negocio.
     */
    public function testDuplicateEmail(): void {
        $db = $this->createMock(PDO::class);
        $stmt = $this->createStub(PDOStatement::class);

        $error = new PDOException('Duplicate entry', 23000);
        $error->errorInfo = [
            '23000',
            1062,
            "Duplicate entry 'test@example.com' for key 'uq_user_email'"
        ];

        $db->method('beginTransaction')->willReturn(true);
        $db->method('inTransaction')->willReturn(true);
        $db->method('prepare')->willReturn($stmt);

        $stmt->method('execute')->willThrowException($error);

        $db->expects($this->once())
            ->method('rollBack')
            ->willReturn(true);

        $db->expects($this->never())
            ->method('commit');

        $service = new RegistrationService($db);

        $this->expectException(
            EmailAlreadyRegisteredException::class
        );

        $service->register(
            'Iñigo',
            'test@example.com',
            'una contraseña larga'
        );
    }

    /**
     * Comprueba que otros errores SQL conservan su excepción original.
     */
    public function testUnrelatedDatabaseError(): void {
        $db = $this->createMock(PDO::class);
        $stmt = $this->createStub(PDOStatement::class);

        $error = new PDOException('Error SQL', 23000);
        $error->errorInfo = [
            '23000',
            1452,
            'Foreign key constraint fails'
        ];

        $db->method('beginTransaction')->willReturn(true);
        $db->method('inTransaction')->willReturn(true);
        $db->method('prepare')->willReturn($stmt);

        $stmt->method('execute')->willThrowException($error);

        $db->expects($this->once())
            ->method('rollBack')
            ->willReturn(true);

        $db->expects($this->never())
            ->method('commit');

        $service = new RegistrationService($db);

        $this->expectException(PDOException::class);

        $service->register(
            'Iñigo',
            'test@example.com',
            'una contraseña larga'
        );
    }
}

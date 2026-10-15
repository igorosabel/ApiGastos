<?php declare(strict_types=1);

use Osumi\OsumiFramework\App\Service\Space\SpaceService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SpaceServiceTest extends TestCase {
    /**
     * Comprueba que el listado usa la membresía del usuario.
     */
    public function testGetUserSpaces(): void {
        $db = $this->createMock(PDO::class);
        $stmt = $this->createMock(PDOStatement::class);

        $db->expects($this->once())
            ->method('prepare')
            ->with($this->stringContains('INNER JOIN `space_member`'))
            ->willReturn($stmt);

        $stmt->expects($this->once())
            ->method('execute')
            ->with(['user_id' => 10])
            ->willReturn(true);

        $stmt->method('fetch')
            ->willReturnOnConsecutiveCalls(
                [
                    'id' => 1,
                    'name' => 'Personal',
                    'type' => 'personal',
                    'owner_id' => 10
                ],
                [
                    'id' => 5,
                    'name' => 'Familia',
                    'type' => 'shared',
                    'owner_id' => 20
                ],
                false
            );

        $service = new SpaceService($db);

        $this->assertSame([
            [
                'id' => 1,
                'name' => 'Personal',
                'type' => 'personal',
                'owner_id' => 10,
                'is_owner' => true
            ],
            [
                'id' => 5,
                'name' => 'Familia',
                'type' => 'shared',
                'owner_id' => 20,
                'is_owner' => false
            ]
        ], $service->getUserSpaces(10));
    }

    /**
     * Comprueba la creación del espacio y de su membresía.
     */
    public function testCreateSharedSpace(): void {
        $db = $this->createMock(PDO::class);
        $space_stmt = $this->createMock(PDOStatement::class);
        $member_stmt = $this->createMock(PDOStatement::class);

        $db->method('prepare')
            ->willReturnOnConsecutiveCalls($space_stmt, $member_stmt);

        $space_stmt->expects($this->once())
            ->method('execute')
            ->with([
                'name' => 'Familia',
                'type' => 'shared',
                'owner_id' => 10
            ])
            ->willReturn(true);

        $member_stmt->expects($this->once())
            ->method('execute')
            ->with([
                'space_id' => 25,
                'user_id' => 10
            ])
            ->willReturn(true);

        $db->method('lastInsertId')->willReturn('25');

        $db->expects($this->once())
            ->method('beginTransaction')
            ->willReturn(true);

        $db->expects($this->once())
            ->method('commit')
            ->willReturn(true);

        $db->expects($this->never())->method('rollBack');

        $service = new SpaceService($db);

        $this->assertSame([
            'id' => 25,
            'name' => 'Familia',
            'type' => 'shared',
            'owner_id' => 10,
            'is_owner' => true
        ], $service->createSharedSpace(10, '  Familia  '));
    }

    /**
     * Comprueba que un nombre inválido no inicia operaciones SQL.
     *
     * @param mixed $name Nombre propuesto.
     */
    #[DataProvider('invalidNames')]
    public function testInvalidSpaceName(mixed $name): void {
        $db = $this->createMock(PDO::class);

        $db->expects($this->never())->method('beginTransaction');

        $service = new SpaceService($db);

        $this->expectException(InvalidArgumentException::class);

        $service->createSharedSpace(10, $name);
    }

    /**
     * Proporciona nombres inválidos.
     *
     * @return iterable<string, array{mixed}>
     */
    public static function invalidNames(): iterable {
        yield 'nulo' => [null];
        yield 'vacío' => [''];
        yield 'espacios' => ['   '];
        yield 'array' => [['Familia']];
        yield 'demasiado largo' => [str_repeat('a', 101)];
    }

    /**
     * Comprueba el rollback si no puede añadirse la membresía.
     */
    public function testRollbackIfMembershipFails(): void {
        $db = $this->createMock(PDO::class);
        $space_stmt = $this->createStub(PDOStatement::class);
        $member_stmt = $this->createStub(PDOStatement::class);

        $db->method('prepare')
            ->willReturnOnConsecutiveCalls($space_stmt, $member_stmt);

        $space_stmt->method('execute')->willReturn(true);
        $member_stmt->method('execute')->willReturn(false);

        $db->method('lastInsertId')->willReturn('25');
        $db->method('beginTransaction')->willReturn(true);
        $db->method('inTransaction')->willReturn(true);

        $db->expects($this->once())
            ->method('rollBack')
            ->willReturn(true);

        $db->expects($this->never())->method('commit');

        $service = new SpaceService($db);

        $this->expectException(RuntimeException::class);

        $service->createSharedSpace(10, 'Familia');
    }
}

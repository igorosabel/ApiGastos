<?php declare(strict_types=1);

use Osumi\OsumiFramework\App\Service\Space\SpaceService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SpaceServiceTest extends TestCase {
    /**
     * Comprueba que se rechazan nombres inválidos sin acceder a la BD.
     *
     * @param string $name Nombre propuesto.
     */
    #[DataProvider('invalidNames')]
    public function testInvalidSpaceName(string $name): void {
        $service = new SpaceService();

        $this->expectException(InvalidArgumentException::class);

        $service->createSharedSpace(10, $name);
    }

    /**
     * Proporciona nombres que no cumplen las reglas de negocio.
     *
     * @return iterable<string, array{string}>
     */
    public static function invalidNames(): iterable {
        yield 'vacío' => [''];
        yield 'espacios' => ['   '];
        yield 'demasiado largo' => [str_repeat('a', 101)];
    }

    /**
     * Comprueba que no se puede listar usando un usuario inválido.
     */
    public function testInvalidUserForListing(): void {
        $service = new SpaceService();

        $this->expectException(InvalidArgumentException::class);

        $service->getUserSpaces(0);
    }

    /**
     * Comprueba que no se puede crear un espacio sin propietario válido.
     */
    public function testInvalidOwnerForCreation(): void {
        $service = new SpaceService();

        $this->expectException(InvalidArgumentException::class);

        $service->createSharedSpace(0, 'Familia');
    }
}

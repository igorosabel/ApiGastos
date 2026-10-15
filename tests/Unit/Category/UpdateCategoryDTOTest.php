<?php declare(strict_types=1);

use Osumi\OsumiFramework\App\DTO\Category\UpdateCategoryDTO;
use Osumi\OsumiFramework\App\Middleware\LoginMiddleware;
use Osumi\OsumiFramework\App\Service\Auth\AuthService;
use Osumi\OsumiFramework\Core\OCore;
use Osumi\OsumiFramework\Core\OMiddleware;
use Osumi\OsumiFramework\Web\ORequest;
use PHPUnit\Framework\TestCase;

final class UpdateCategoryDTOTest extends TestCase {
    private bool $core_existed = false;
    private mixed $previous_core = null;

    /**
     * Prepara el entorno aislado de autenticación.
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
     * Restaura el entorno anterior.
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
     * Comprueba una edición válida.
     */
    public function testValidUpdate(): void {
        $this->authenticateAs(10);

        $dto = $this->createDto([
            'spaceId' => 25,
            'categoryId' => 30,
            'name' => 'Supermercados',
            'parentId' => 5,
            'sortOrder' => 2
        ]);

        $this->assertTrue($dto->isValid());
        $this->assertSame(25, $dto->spaceId);
        $this->assertSame(30, $dto->categoryId);
        $this->assertSame('Supermercados', $dto->name);
        $this->assertSame(5, $dto->parentId);
        $this->assertSame(2, $dto->sortOrder);
        $this->assertSame(10, $dto->idUser);
    }

    /**
     * Comprueba que puede elegirse una categoría raíz.
     */
    public function testRootCategory(): void {
        $this->authenticateAs(10);

        $dto = $this->createDto([
            'spaceId' => 25,
            'categoryId' => 30,
            'name' => 'Hogar',
            'parentId' => null,
            'sortOrder' => 0
        ]);

        $this->assertTrue($dto->isValid());
        $this->assertNull($dto->parentId);
    }

    /**
     * Comprueba que el usuario autenticado no puede falsificarse.
     */
    public function testCannotOverrideAuthenticatedUser(): void {
        $this->authenticateAs(10);

        $dto = $this->createDto([
            'spaceId' => 25,
            'categoryId' => 30,
            'name' => 'Hogar',
            'sortOrder' => 0,
            'idUser' => 999
        ]);

        $this->assertTrue($dto->isValid());
        $this->assertSame(10, $dto->idUser);
    }

    /**
     * Comprueba que la autenticación es obligatoria.
     */
    public function testAuthenticationRequired(): void {
        $dto = $this->createDto([
            'spaceId' => 25,
            'categoryId' => 30,
            'name' => 'Hogar',
            'sortOrder' => 0,
            'idUser' => 999
        ]);

        $this->assertFalse($dto->isValid());
        $this->assertNull($dto->idUser);
    }

    /**
     * Comprueba que todos los campos obligatorios se validan.
     */
    public function testRequiredFields(): void {
        $this->authenticateAs(10);

        foreach ([
            'spaceId',
            'categoryId',
            'name',
            'sortOrder'
        ] as $missing) {
            $params = [
                'spaceId' => 25,
                'categoryId' => 30,
                'name' => 'Hogar',
                'sortOrder' => 0
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
     * Publica el usuario autenticado mediante LoginMiddleware.
     *
     * @param int $id Identificador del usuario.
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
     * Construye un DTO desde una petición POST.
     *
     * @param array<string, mixed> $params Datos de entrada.
     * @return UpdateCategoryDTO DTO resultante.
     */
    private function createDto(array $params): UpdateCategoryDTO {
        return new UpdateCategoryDTO(
            new ORequest([
                'method' => 'POST',
                'headers' => [],
                'params' => $params
            ])
        );
    }
}

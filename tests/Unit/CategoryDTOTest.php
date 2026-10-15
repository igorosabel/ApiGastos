<?php declare(strict_types=1);

use Osumi\OsumiFramework\App\DTO\CreateCategoryDTO;
use Osumi\OsumiFramework\App\DTO\GetCategoriesDTO;
use Osumi\OsumiFramework\App\Middleware\LoginMiddleware;
use Osumi\OsumiFramework\App\Service\Auth\AuthService;
use Osumi\OsumiFramework\Core\OCore;
use Osumi\OsumiFramework\Core\OMiddleware;
use Osumi\OsumiFramework\Web\ORequest;
use PHPUnit\Framework\TestCase;

final class CategoryDTOTest extends TestCase {
    private bool $core_existed = false;
    private mixed $previous_core = null;

    /**
     * Prepara el contexto de OFW.
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
     * Restaura el contexto de OFW.
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
     * Comprueba los datos válidos para crear una categoría.
     */
    public function testCreateCategory(): void {
        $this->authenticateAs(10);

        $dto = new CreateCategoryDTO(
            $this->request('POST', [
                'spaceId' => 25,
                'name' => 'Alimentación',
                'defaultType' => 'expense',
                'parentId' => null,
                'sortOrder' => 0
            ])
        );

        $this->assertTrue($dto->isValid());
        $this->assertSame(25, $dto->spaceId);
        $this->assertSame('Alimentación', $dto->name);
        $this->assertSame('expense', $dto->defaultType);
        $this->assertNull($dto->parentId);
        $this->assertSame(0, $dto->sortOrder);
        $this->assertSame(10, $dto->idUser);
    }

    /**
     * Comprueba que los campos opcionales pueden omitirse.
     */
    public function testOptionalFields(): void {
        $this->authenticateAs(10);

        $dto = new CreateCategoryDTO(
            $this->request('POST', [
                'spaceId' => 25,
                'name' => 'Ingresos',
                'defaultType' => 'income'
            ])
        );

        $this->assertTrue($dto->isValid());
        $this->assertNull($dto->parentId);
        $this->assertNull($dto->sortOrder);
    }

    /**
     * Comprueba la identidad del middleware.
     */
    public function testCannotOverrideAuthenticatedUser(): void {
        $this->authenticateAs(10);

        $dto = new CreateCategoryDTO(
            $this->request('POST', [
                'spaceId' => 25,
                'name' => 'Hogar',
                'defaultType' => 'expense',
                'idUser' => 999
            ])
        );

        $this->assertTrue($dto->isValid());
        $this->assertSame(10, $dto->idUser);
    }

    /**
     * Comprueba que el listado necesita autenticación.
     */
    public function testListRequiresAuthentication(): void {
        $dto = new GetCategoriesDTO(
            $this->request('GET', [
                'spaceId' => 25,
                'idUser' => 999
            ])
        );

        $this->assertFalse($dto->isValid());
        $this->assertNull($dto->idUser);
    }

    /**
     * Comprueba que el espacio es obligatorio para listar.
     */
    public function testListRequiresSpaceId(): void {
        $this->authenticateAs(10);

        $dto = new GetCategoriesDTO(
            $this->request('GET', [])
        );

        $this->assertFalse($dto->isValid());
    }

    /**
     * Comprueba los campos obligatorios para crear.
     */
    public function testCreateRequiresFields(): void {
        $this->authenticateAs(10);

        $dto = new CreateCategoryDTO(
            $this->request('POST', [
                'spaceId' => 25
            ])
        );

        $this->assertFalse($dto->isValid());
        $this->assertNull($dto->name);
        $this->assertNull($dto->defaultType);
    }

    /**
     * Publica una identidad mediante LoginMiddleware.
     *
     * @param int $user_id Identificador autenticado.
     */
    private function authenticateAs(int $user_id): void {
        $service = $this->createMock(AuthService::class);

        $service->expects($this->once())
            ->method('authenticateToken')
            ->with('token-prueba')
            ->willReturn([
                'id' => $user_id,
                'name' => 'Usuario prueba',
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
     * Construye una petición.
     *
     * @param string $method Método HTTP.
     * @param array<string, mixed> $params Parámetros.
     * @return ORequest Petición.
     */
    private function request(string $method, array $params): ORequest {
        return new ORequest([
            'method' => $method,
            'headers' => [],
            'params' => $params
        ]);
    }
}

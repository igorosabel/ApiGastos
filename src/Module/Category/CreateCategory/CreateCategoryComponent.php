<?php declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Module\Category\CreateCategory;

use DomainException;
use InvalidArgumentException;
use Osumi\OsumiFramework\App\Component\Model\Category\CategoryComponent;
use Osumi\OsumiFramework\App\DTO\CreateCategoryDTO;
use Osumi\OsumiFramework\App\Service\Category\CategoryService;
use Osumi\OsumiFramework\Core\OComponent;
use Osumi\OsumiFramework\Core\OMiddleware;

class CreateCategoryComponent extends OComponent {
    private CategoryService $category_service;

    public bool $success = false;
    public ?CategoryComponent $category = null;
    public ?string $error = null;
    public ?string $message = null;

    /**
     * Inicializa el servicio.
     */
    public function __construct() {
        parent::__construct();
        $this->category_service = inject(CategoryService::class);
    }

    /**
     * Crea una categoría del espacio.
     *
     * @param CreateCategoryDTO $dto Datos de entrada.
     * @return void
     */
    public function run(CreateCategoryDTO $dto): void {
        if (
            !$dto->isValid() ||
            $dto->spaceId === null ||
            $dto->idUser === null ||
            $dto->name === null ||
            $dto->defaultType === null
        ) {
            $this->error = 'validation_error';
            $this->message = 'Faltan datos obligatorios.';
            OMiddleware::setStatusCode(400);
            return;
        }

        try {
            $category = $this->category_service->create(
                $dto->spaceId,
                $dto->idUser,
                $dto->name,
                $dto->defaultType,
                $dto->parentId,
                $dto->sortOrder ?? 0
            );

            $this->category = new CategoryComponent([
                'category' => $category
            ]);
            $this->success = true;
            OMiddleware::setStatusCode(201);
        }
        catch (InvalidArgumentException $e) {
            $this->error = 'validation_error';
            $this->message = $e->getMessage();
            OMiddleware::setStatusCode(400);
        }
        catch (DomainException $e) {
            $this->error = 'forbidden';
            $this->message = $e->getMessage();
            OMiddleware::setStatusCode(403);
        }
    }
}

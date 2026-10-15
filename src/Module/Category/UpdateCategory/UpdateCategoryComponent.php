<?php declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Module\Category\UpdateCategory;

use DomainException;
use InvalidArgumentException;
use Osumi\OsumiFramework\App\Component\Model\Category\CategoryComponent;
use Osumi\OsumiFramework\App\DTO\UpdateCategoryDTO;
use Osumi\OsumiFramework\App\Service\Category\CategoryService;
use Osumi\OsumiFramework\Core\OComponent;
use Osumi\OsumiFramework\Core\OMiddleware;

class UpdateCategoryComponent extends OComponent {
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
     * Actualiza una categoría del espacio autorizado.
     *
     * @param UpdateCategoryDTO $dto Datos de entrada.
     * @return void
     */
    public function run(UpdateCategoryDTO $dto): void {
        if (
            !$dto->isValid() ||
            $dto->spaceId === null ||
            $dto->categoryId === null ||
            $dto->idUser === null ||
            $dto->name === null ||
            $dto->sortOrder === null
        ) {
            $this->error = 'validation_error';
            $this->message = 'Faltan datos obligatorios.';
            OMiddleware::setStatusCode(400);
            return;
        }

        try {
            $updated = $this->category_service->update(
                $dto->spaceId,
                $dto->idUser,
                $dto->categoryId,
                $dto->name,
                $dto->parentId,
                $dto->sortOrder
            );

            $this->category = new CategoryComponent([
                'category' => $updated
            ]);
            $this->success = true;
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

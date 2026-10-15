<?php declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Module\Category\DeleteCategory;

use DomainException;
use InvalidArgumentException;
use LogicException;
use Osumi\OsumiFramework\App\DTO\Category\DeleteCategoryDTO;
use Osumi\OsumiFramework\App\Service\Category\CategoryService;
use Osumi\OsumiFramework\Core\OComponent;
use Osumi\OsumiFramework\Core\OMiddleware;

class DeleteCategoryComponent extends OComponent {
    private CategoryService $category_service;

    public bool $success = false;
    public ?string $error = null;
    public ?string $message = null;

    /**
     * Inicializa el servicio de categorías.
     */
    public function __construct() {
        parent::__construct();
        $this->category_service = inject(CategoryService::class);
    }

    /**
     * Elimina una categoría sin dependencias.
     *
     * @param DeleteCategoryDTO $dto Datos de entrada.
     * @return void
     */
    public function run(DeleteCategoryDTO $dto): void {
        if (
            !$dto->isValid() ||
            $dto->spaceId === null ||
            $dto->categoryId === null ||
            $dto->idUser === null
        ) {
            $this->error = 'validation_error';
            $this->message = 'Faltan datos obligatorios.';
            OMiddleware::setStatusCode(400);
            return;
        }

        try {
            $this->category_service->delete(
                $dto->spaceId,
                $dto->idUser,
                $dto->categoryId
            );

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
        catch (LogicException $e) {
            $this->error = 'category_in_use';
            $this->message = $e->getMessage();
            OMiddleware::setStatusCode(409);
        }
    }
}

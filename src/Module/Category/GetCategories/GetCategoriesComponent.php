<?php declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Module\Category\GetCategories;

use DomainException;
use InvalidArgumentException;
use Osumi\OsumiFramework\App\Component\Model\CategoryList\CategoryListComponent;
use Osumi\OsumiFramework\App\DTO\Category\GetCategoriesDTO;
use Osumi\OsumiFramework\App\Service\Category\CategoryService;
use Osumi\OsumiFramework\Core\OComponent;
use Osumi\OsumiFramework\Core\OMiddleware;

class GetCategoriesComponent extends OComponent {
    private CategoryService $category_service;

    public bool $success = false;
    public ?CategoryListComponent $list = null;
    public ?string $error = null;
    public ?string $message = null;

    /**
     * Inicializa las dependencias.
     */
    public function __construct() {
        parent::__construct();
        $this->category_service = inject(CategoryService::class);
        $this->list = new CategoryListComponent();
    }

    /**
     * Consulta las categorías del espacio.
     *
     * @param GetCategoriesDTO $dto Datos de entrada.
     * @return void
     */
    public function run(GetCategoriesDTO $dto): void {
        if (
            !$dto->isValid() ||
            $dto->spaceId === null ||
            $dto->idUser === null
        ) {
            $this->error = 'validation_error';
            $this->message = 'El espacio es obligatorio.';
            OMiddleware::setStatusCode(400);
            return;
        }

        try {
            $this->list->list = $this->category_service->getBySpace(
                $dto->spaceId,
                $dto->idUser
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
    }
}

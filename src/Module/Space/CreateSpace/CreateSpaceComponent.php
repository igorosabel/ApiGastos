<?php declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Module\Space\CreateSpace;

use InvalidArgumentException;
use Osumi\OsumiFramework\App\Component\Model\Space\SpaceComponent;
use Osumi\OsumiFramework\App\DTO\CreateSpaceDTO;
use Osumi\OsumiFramework\App\Service\Space\SpaceService;
use Osumi\OsumiFramework\Core\OComponent;
use Osumi\OsumiFramework\Core\OMiddleware;

class CreateSpaceComponent extends OComponent {
    private SpaceService $space_service;

    public bool $success = false;
    public ?SpaceComponent $space = null;
    public ?string $error = null;
    public ?string $message = null;

    /**
     * Inicializa las dependencias del componente.
     */
    public function __construct() {
        parent::__construct();
        $this->space_service = inject(SpaceService::class);
    }

    /**
     * Crea un espacio compartido.
     *
     * @param CreateSpaceDTO $dto Datos validados de entrada.
     */
    public function run(CreateSpaceDTO $dto): void {
        if (
            !$dto->isValid() ||
            $dto->idUser === null ||
            $dto->name === null
        ) {
            $this->error = 'validation_error';
            $this->message = 'El nombre del espacio es obligatorio.';
            OMiddleware::setStatusCode(400);
            return;
        }

        try {
            $space = $this->space_service->createSharedSpace(
                $dto->idUser,
                $dto->name
            );

            $this->space = new SpaceComponent([
                'space' => $space,
                'user_id' => $dto->idUser
            ]);

            $this->success = true;
            OMiddleware::setStatusCode(201);
        }
        catch (InvalidArgumentException $e) {
            $this->error = 'validation_error';
            $this->message = $e->getMessage();
            OMiddleware::setStatusCode(400);
        }
    }
}

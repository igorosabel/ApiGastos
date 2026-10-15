<?php declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Module\Space\CreateSpace;

use InvalidArgumentException;
use Osumi\OsumiFramework\App\Service\Space\SpaceService;
use Osumi\OsumiFramework\Core\OComponent;
use Osumi\OsumiFramework\Core\OMiddleware;
use Osumi\OsumiFramework\Web\ORequest;

class CreateSpaceComponent extends OComponent {
    private SpaceService $space_service;

    public bool $success = false;
    public ?int $id = null;
    public ?string $name = null;
    public ?string $type = null;
    public ?int $owner_id = null;
    public bool $is_owner = false;
    public ?string $error = null;
    public ?string $message = null;

    /**
     * Inicializa el servicio de espacios mediante OFW.
     */
    public function __construct() {
        parent::__construct();
        $this->space_service = inject(SpaceService::class);
    }

    /**
     * Crea un espacio compartido para el usuario autenticado.
     *
     * @param ORequest $req Petición HTTP.
     * @return void
     */
    public function run(ORequest $req): void {
        $user_id = $req->getMiddlewareValue('Login', 'id');

        if (!is_int($user_id) || $user_id <= 0) {
            OMiddleware::setStatusCode(401);
            return;
        }

        try {
            $space = $this->space_service->createSharedSpace(
                $user_id,
                $req->getParam('name')
            );

            $this->success = true;
            $this->id = $space['id'];
            $this->name = $space['name'];
            $this->type = $space['type'];
            $this->owner_id = $space['owner_id'];
            $this->is_owner = $space['is_owner'];

            OMiddleware::setStatusCode(201);
        }
        catch (InvalidArgumentException $e) {
            $this->error = 'validation_error';
            $this->message = $e->getMessage();

            OMiddleware::setStatusCode(400);
        }
    }
}

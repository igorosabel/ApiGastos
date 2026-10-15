<?php declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Module\Space\GetSpaces;

use Osumi\OsumiFramework\App\Component\Model\SpaceList\SpaceListComponent;
use Osumi\OsumiFramework\App\Service\Space\SpaceService;
use Osumi\OsumiFramework\Core\OComponent;
use Osumi\OsumiFramework\Core\OMiddleware;
use Osumi\OsumiFramework\Web\ORequest;

class GetSpacesComponent extends OComponent {
    private SpaceService $space_service;

    public bool $success = false;
    public ?SpaceListComponent $list = null;

    /**
     * Inicializa el servicio y el componente de listado.
     */
    public function __construct() {
        parent::__construct();
        $this->space_service = inject(SpaceService::class);
        $this->list = new SpaceListComponent();
    }

    /**
     * Obtiene los espacios del usuario autenticado.
     *
     * @param ORequest $req Petición HTTP.
     */
    public function run(ORequest $req): void {
        $user_id = $req->getMiddlewareValue('Login', 'id');

        if (!is_int($user_id) || $user_id <= 0) {
            OMiddleware::setStatusCode(401);
            return;
        }

        $this->list->list = $this->space_service->getUserSpaces($user_id);
        $this->list->user_id = $user_id;
        $this->success = true;
    }
}

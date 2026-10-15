<?php declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Module\Space\GetSpaces;

use Osumi\OsumiFramework\App\Service\Space\SpaceService;
use Osumi\OsumiFramework\Core\OComponent;
use Osumi\OsumiFramework\Core\OMiddleware;
use Osumi\OsumiFramework\Web\ORequest;

class GetSpacesComponent extends OComponent {
    private SpaceService $space_service;

    public bool $success = false;
    public string $spaces_json = '[]';

    /**
     * Inicializa el servicio de espacios mediante OFW.
     */
    public function __construct() {
        parent::__construct();
        $this->space_service = inject(SpaceService::class);
    }

    /**
     * Devuelve los espacios del usuario autenticado.
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

        $spaces = $this->space_service->getUserSpaces($user_id);

        $this->spaces_json = json_encode(
            $spaces,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE
        );
        $this->success = true;
    }
}

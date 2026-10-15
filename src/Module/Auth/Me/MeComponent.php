<?php declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Module\Auth\Me;

use Osumi\OsumiFramework\Core\OComponent;
use Osumi\OsumiFramework\Core\OMiddleware;
use Osumi\OsumiFramework\Web\ORequest;

class MeComponent extends OComponent {
    public bool $success = false;
    public ?int $id = null;
    public ?string $name = null;
    public ?string $email = null;

    /**
     * Prepara los datos del usuario autenticado.
     *
     * @param ORequest $req Petición HTTP.
     * @return void
     */
    public function run(ORequest $req): void {
        $id = $req->getMiddlewareValue('Login', 'id');
        $name = $req->getMiddlewareValue('Login', 'name');
        $email = $req->getMiddlewareValue('Login', 'email');

        if (
            !is_int($id) ||
            !is_string($name) ||
            !is_string($email)
        ) {
            OMiddleware::setStatusCode(401);
            return;
        }

        $this->success = true;
        $this->id = $id;
        $this->name = $name;
        $this->email = $email;
    }
}

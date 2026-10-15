<?php declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Module\Auth\Login;

use Osumi\OsumiFramework\App\Service\Auth\AuthService;
use Osumi\OsumiFramework\Core\OComponent;
use Osumi\OsumiFramework\Core\OMiddleware;
use Osumi\OsumiFramework\Web\ORequest;

class LoginComponent extends OComponent {
    private AuthService $auth_service;

    public bool $success = false;
    public ?string $token = null;
    public ?int $expires_at = null;
    public ?int $user_id = null;
    public ?string $name = null;
    public ?string $email = null;
    public ?string $error = null;
    public ?string $message = null;

    /**
     * Inicializa el componente e inyecta el servicio.
     */
    public function __construct() {
        parent::__construct();
        $this->auth_service = inject(AuthService::class);
    }

    /**
     * Atiende la solicitud pública de inicio de sesión.
     *
     * @param ORequest $req Petición HTTP.
     * @return void
     */
    public function run(ORequest $req): void {
        $email = $req->getParam('email');
        $password = $req->getParam('password');

        if (
            !is_string($email) ||
            trim($email) === '' ||
            !is_string($password) ||
            $password === ''
        ) {
            $this->error = 'validation_error';
            $this->message = 'El correo y la contraseña son obligatorios.';
            OMiddleware::setStatusCode(400);
            return;
        }

        $result = $this->auth_service->login($email, $password);

        if ($result === null) {
            $this->error = 'invalid_credentials';
            $this->message = 'Correo electrónico o contraseña incorrectos.';
            OMiddleware::setStatusCode(401);
            return;
        }

        $this->success = true;
        $this->token = $result['token'];
        $this->expires_at = $result['expires_at'];
        $this->user_id = $result['user_id'];
        $this->name = $result['name'];
        $this->email = $result['email'];

        OMiddleware::setStatusCode(200);
    }
}

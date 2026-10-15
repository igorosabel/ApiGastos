<?php declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Module\Auth\Register;

use InvalidArgumentException;
use Osumi\OsumiFramework\App\Service\Auth\EmailAlreadyRegisteredException;
use Osumi\OsumiFramework\App\Service\Auth\RegistrationService;
use Osumi\OsumiFramework\App\Service\Auth\RegistrationValidator;
use Osumi\OsumiFramework\Core\OComponent;
use Osumi\OsumiFramework\Core\OMiddleware;
use Osumi\OsumiFramework\Web\ORequest;

final class RegisterComponent extends OComponent {
    private RegistrationService $registration_service;

    public bool $success = false;
    public ?int $user_id = null;
    public ?int $space_id = null;
    public ?string $error = null;
    public ?string $message = null;

    /**
     * Inicializa el componente e inyecta el servicio de registro.
     */
    public function __construct() {
        parent::__construct();
        $this->registration_service = inject(RegistrationService::class);
    }

    /**
     * Atiende la solicitud pública de registro de usuarios.
     *
     * @param ORequest $req Petición HTTP.
     * @return void
     */
    public function run(ORequest $req): void {
        try {
            $data = RegistrationValidator::validate(
                $req->getParam('name'),
                $req->getParam('email'),
                $req->getParam('password')
            );

            $result = $this->registration_service->register(
                $data['name'],
                $data['email'],
                $data['password']
            );

            $this->success = true;
            $this->user_id = $result['user_id'];
            $this->space_id = $result['space_id'];

            OMiddleware::setStatusCode(201);
        }
        catch (InvalidArgumentException $e) {
            $this->error = 'validation_error';
            $this->message = $e->getMessage();

            OMiddleware::setStatusCode(400);
        }
        catch (EmailAlreadyRegisteredException $e) {
            $this->error = 'email_already_registered';
            $this->message = $e->getMessage();

            OMiddleware::setStatusCode(409);
        }
    }
}

<?php declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Module\Space\CreateInvitation;

use DomainException;
use InvalidArgumentException;
use LogicException;
use Osumi\OsumiFramework\App\DTO\CreateSpaceInvitationDTO;
use Osumi\OsumiFramework\App\Service\Space\SpaceInvitationService;
use Osumi\OsumiFramework\Core\OComponent;
use Osumi\OsumiFramework\Core\OMiddleware;

class CreateInvitationComponent extends OComponent {
    private SpaceInvitationService $invitation_service;

    public bool $success = false;
    public ?int $invitation_id = null;
    public ?int $space_id = null;
    public ?string $email = null;
    public ?string $token = null;
    public ?string $expires_at = null;
    public ?string $error = null;
    public ?string $message = null;

    /**
     * Inicializa el servicio de invitaciones.
     */
    public function __construct() {
        parent::__construct();
        $this->invitation_service = inject(
            SpaceInvitationService::class
        );
    }

    /**
     * Crea una invitación para un espacio compartido.
     *
     * @param CreateSpaceInvitationDTO $dto Datos de entrada.
     * @return void
     */
    public function run(CreateSpaceInvitationDTO $dto): void {
        if (
            !$dto->isValid() ||
            $dto->spaceId === null ||
            $dto->email === null ||
            $dto->idUser === null
        ) {
            $this->error = 'validation_error';
            $this->message = 'Faltan datos obligatorios.';
            OMiddleware::setStatusCode(400);
            return;
        }

        try {
            $result = $this->invitation_service->create(
                $dto->spaceId,
                $dto->idUser,
                $dto->email
            );

            $invitation = $result['invitation'];

            $this->invitation_id = $invitation->id;
            $this->space_id = $invitation->id_space;
            $this->email = $invitation->email;
            $this->token = $result['token'];
            $this->expires_at = $invitation->expires_at;
            $this->success = true;

            OMiddleware::setStatusCode(201);
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
            $this->error = 'invitation_conflict';
            $this->message = $e->getMessage();
            OMiddleware::setStatusCode(409);
        }
    }
}

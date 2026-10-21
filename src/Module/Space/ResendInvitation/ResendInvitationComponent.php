<?php declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Module\Space\ResendInvitation;

use DomainException;
use InvalidArgumentException;
use LogicException;
use Osumi\OsumiFramework\App\DTO\Space\ResendSpaceInvitationDTO;
use Osumi\OsumiFramework\App\Service\Space\SpaceInvitationNotificationService;
use Osumi\OsumiFramework\Core\OComponent;
use Osumi\OsumiFramework\Core\OMiddleware;

class ResendInvitationComponent extends OComponent {
    private SpaceInvitationNotificationService $invitation_service;

    public bool $success = false;
    public ?int $invitation_id = null;
    public ?int $space_id = null;
    public ?string $email = null;
    public ?string $token = null;
    public ?string $expires_at = null;
    public bool $email_sent = false;
    public ?string $error = null;
    public ?string $message = null;

    /**
     * Inicializa el coordinador de invitaciones.
     */
    public function __construct() {
        parent::__construct();

        $this->invitation_service = inject(
            SpaceInvitationNotificationService::class
        );
    }

    /**
     * Reenvía una invitación pendiente.
     *
     * @param ResendSpaceInvitationDTO $dto Datos validados.
     */
    public function run(ResendSpaceInvitationDTO $dto): void {
        if (
            !$dto->isValid() ||
            $dto->invitationId === null ||
            $dto->idUser === null
        ) {
            $this->error = 'validation_error';
            $this->message = 'La invitación es obligatoria.';
            OMiddleware::setStatusCode(400);
            return;
        }

        try {
            $result = $this->invitation_service->resendAndSend(
                $dto->invitationId,
                $dto->idUser
            );

            $invitation = $result['invitation'];

            $this->invitation_id = $invitation->id;
            $this->space_id = $invitation->id_space;
            $this->email = $invitation->email;
            $this->token = $result['token'];
            $this->expires_at = $invitation->expires_at;
            $this->email_sent = $result['email_sent'];
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
            $this->error = 'invitation_conflict';
            $this->message = $e->getMessage();
            OMiddleware::setStatusCode(409);
        }
    }
}

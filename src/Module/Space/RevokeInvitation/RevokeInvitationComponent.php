<?php declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Module\Space\RevokeInvitation;

use DomainException;
use InvalidArgumentException;
use LogicException;
use Osumi\OsumiFramework\App\Component\Model\SpaceInvitation\SpaceInvitationComponent;
use Osumi\OsumiFramework\App\DTO\RevokeSpaceInvitationDTO;
use Osumi\OsumiFramework\App\Service\Space\SpaceInvitationService;
use Osumi\OsumiFramework\Core\OComponent;
use Osumi\OsumiFramework\Core\OMiddleware;

class RevokeInvitationComponent extends OComponent {
    private SpaceInvitationService $invitation_service;

    public bool $success = false;
    public ?SpaceInvitationComponent $invitation = null;
    public ?string $error = null;
    public ?string $message = null;

    /**
     * Inicializa el servicio.
     */
    public function __construct() {
        parent::__construct();
        $this->invitation_service = inject(
            SpaceInvitationService::class
        );
    }

    /**
     * Revoca una invitación pendiente.
     *
     * @param RevokeSpaceInvitationDTO $dto Datos validados.
     * @return void
     */
    public function run(RevokeSpaceInvitationDTO $dto): void {
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
            $invitation = $this->invitation_service->revoke(
                $dto->invitationId,
                $dto->idUser
            );

            $this->invitation = new SpaceInvitationComponent([
                'invitation' => $invitation
            ]);
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

<?php declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Module\Space\AcceptInvitation;

use DomainException;
use InvalidArgumentException;
use LogicException;
use Osumi\OsumiFramework\App\Component\Model\Space\SpaceComponent;
use Osumi\OsumiFramework\App\DTO\Space\AcceptSpaceInvitationDTO;
use Osumi\OsumiFramework\App\Service\Space\SpaceInvitationService;
use Osumi\OsumiFramework\Core\OComponent;
use Osumi\OsumiFramework\Core\OMiddleware;

class AcceptInvitationComponent extends OComponent {
    private SpaceInvitationService $invitation_service;

    public bool $success = false;
    public ?SpaceComponent $space = null;
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
     * Acepta la invitación del usuario autenticado.
     *
     * @param AcceptSpaceInvitationDTO $dto Datos de entrada.
     * @return void
     */
    public function run(AcceptSpaceInvitationDTO $dto): void {
        if (
            !$dto->isValid() ||
            $dto->token === null ||
            $dto->idUser === null
        ) {
            $this->error = 'validation_error';
            $this->message = 'El token es obligatorio.';
            OMiddleware::setStatusCode(400);
            return;
        }

        try {
            $space = $this->invitation_service->accept(
                $dto->token,
                $dto->idUser
            );

            $this->space = new SpaceComponent([
                'space' => $space,
                'user_id' => $dto->idUser
            ]);
            $this->success = true;

            OMiddleware::setStatusCode(200);
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

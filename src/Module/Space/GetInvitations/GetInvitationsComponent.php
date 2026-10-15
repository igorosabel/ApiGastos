<?php declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Module\Space\GetInvitations;

use DomainException;
use InvalidArgumentException;
use Osumi\OsumiFramework\App\Component\Model\SpaceInvitationList\SpaceInvitationListComponent;
use Osumi\OsumiFramework\App\DTO\Space\GetSpaceInvitationsDTO;
use Osumi\OsumiFramework\App\Service\Space\SpaceInvitationService;
use Osumi\OsumiFramework\Core\OComponent;
use Osumi\OsumiFramework\Core\OMiddleware;

class GetInvitationsComponent extends OComponent {
    private SpaceInvitationService $invitation_service;

    public bool $success = false;
    public ?SpaceInvitationListComponent $list = null;
    public ?string $error = null;
    public ?string $message = null;

    /**
     * Inicializa los componentes y servicios.
     */
    public function __construct() {
        parent::__construct();
        $this->invitation_service = inject(
            SpaceInvitationService::class
        );
        $this->list = new SpaceInvitationListComponent();
    }

    /**
     * Lista invitaciones del espacio autorizado.
     *
     * @param GetSpaceInvitationsDTO $dto Datos de entrada.
     * @return void
     */
    public function run(GetSpaceInvitationsDTO $dto): void {
        if (
            !$dto->isValid() ||
            $dto->spaceId === null ||
            $dto->idUser === null
        ) {
            $this->error = 'validation_error';
            $this->message = 'El espacio es obligatorio.';
            OMiddleware::setStatusCode(400);
            return;
        }

        try {
            $this->list->list = $this->invitation_service->getBySpace(
                $dto->spaceId,
                $dto->idUser
            );

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
    }
}

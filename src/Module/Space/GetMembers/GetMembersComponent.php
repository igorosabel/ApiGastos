<?php declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Module\Space\GetMembers;

use DomainException;
use InvalidArgumentException;
use Osumi\OsumiFramework\App\Component\Model\SpaceMemberList\SpaceMemberListComponent;
use Osumi\OsumiFramework\App\DTO\Space\GetSpaceMembersDTO;
use Osumi\OsumiFramework\App\Service\Space\SpaceMemberService;
use Osumi\OsumiFramework\Core\OComponent;
use Osumi\OsumiFramework\Core\OMiddleware;

class GetMembersComponent extends OComponent {
    private SpaceMemberService $member_service;

    public bool $success = false;
    public ?SpaceMemberListComponent $list = null;
    public ?string $error = null;
    public ?string $message = null;

    /**
     * Inicializa las dependencias.
     */
    public function __construct() {
        parent::__construct();

        $this->member_service = inject(
            SpaceMemberService::class
        );

        $this->list = new SpaceMemberListComponent();
    }

    /**
     * Devuelve los miembros del espacio autorizado.
     *
     * @param GetSpaceMembersDTO $dto Datos de la petición.
     */
    public function run(GetSpaceMembersDTO $dto): void {
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
            $this->list->list = $this->member_service->getMembers(
                $dto->spaceId,
                $dto->idUser
            );
            $this->list->owner_id = $dto->idUser;
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

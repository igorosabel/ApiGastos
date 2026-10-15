<?php declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Module\Space\LeaveSpace;

use DomainException;
use InvalidArgumentException;
use LogicException;
use Osumi\OsumiFramework\App\DTO\Space\LeaveSpaceDTO;
use Osumi\OsumiFramework\App\Service\Space\SpaceMemberService;
use Osumi\OsumiFramework\Core\OComponent;
use Osumi\OsumiFramework\Core\OMiddleware;

class LeaveSpaceComponent extends OComponent {
    private SpaceMemberService $member_service;

    public bool $success = false;
    public ?string $error = null;
    public ?string $message = null;

    /**
     * Inicializa el servicio.
     */
    public function __construct() {
        parent::__construct();
        $this->member_service = inject(SpaceMemberService::class);
    }

    /**
     * Da de baja al usuario autenticado del espacio.
     *
     * @param LeaveSpaceDTO $dto Datos de entrada.
     */
    public function run(LeaveSpaceDTO $dto): void {
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
            $this->member_service->leave(
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
        catch (LogicException $e) {
            $this->error = 'membership_conflict';
            $this->message = $e->getMessage();
            OMiddleware::setStatusCode(409);
        }
    }
}

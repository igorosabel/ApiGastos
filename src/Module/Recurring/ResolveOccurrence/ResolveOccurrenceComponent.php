<?php declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Module\Recurring\ResolveOccurrence;

use DomainException;
use InvalidArgumentException;
use LogicException;
use Osumi\OsumiFramework\App\Component\Model\Occurrence\OccurrenceComponent;
use Osumi\OsumiFramework\App\DTO\Recurring\ResolveOccurrenceDTO;
use Osumi\OsumiFramework\App\Service\Recurring\OccurrenceService;
use Osumi\OsumiFramework\Core\OComponent;
use Osumi\OsumiFramework\Core\OMiddleware;

class ResolveOccurrenceComponent extends OComponent {
    private OccurrenceService $occurrence_service;

    public bool $success = false;
    public ?OccurrenceComponent $occurrence = null;
    public ?string $error = null;
    public ?string $message = null;

    /**
     * Inicializa el servicio de ocurrencias.
     */
    public function __construct() {
        parent::__construct();
        $this->occurrence_service = inject(OccurrenceService::class);
    }

    /**
     * Confirma u omite una mensualidad pendiente.
     *
     * @param ResolveOccurrenceDTO $dto Petición.
     * @return void
     */
    public function run(ResolveOccurrenceDTO $dto): void {
        if (
            !$dto->isValid() ||
            $dto->spaceId === null ||
            $dto->occurrenceId === null ||
            $dto->action === null ||
            $dto->idUser === null
        ) {
            $this->error = 'validation_error';
            $this->message = 'Faltan datos obligatorios.';
            OMiddleware::setStatusCode(400);
            return;
        }

        try {
            $resolved = $this->occurrence_service->resolve(
                $dto->spaceId,
                $dto->idUser,
                $dto->occurrenceId,
                $dto->action
            );

            $this->occurrence = new OccurrenceComponent([
                'occurrence' => $resolved
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
            $this->error = 'occurrence_conflict';
            $this->message = $e->getMessage();
            OMiddleware::setStatusCode(409);
        }
    }
}

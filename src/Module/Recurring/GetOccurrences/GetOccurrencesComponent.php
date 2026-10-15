<?php declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Module\Recurring\GetOccurrences;

use DomainException;
use InvalidArgumentException;
use Osumi\OsumiFramework\App\Component\Model\OccurrenceList\OccurrenceListComponent;
use Osumi\OsumiFramework\App\DTO\Recurring\GetOccurrencesDTO;
use Osumi\OsumiFramework\App\Service\Recurring\OccurrenceService;
use Osumi\OsumiFramework\Core\OComponent;
use Osumi\OsumiFramework\Core\OMiddleware;

class GetOccurrencesComponent extends OComponent {
    private OccurrenceService $occurrence_service;

    public bool $success = false;
    public ?OccurrenceListComponent $list = null;
    public ?string $error = null;
    public ?string $message = null;

    /**
     * Inicializa el servicio y el listado.
     */
    public function __construct() {
        parent::__construct();
        $this->occurrence_service = inject(OccurrenceService::class);
        $this->list = new OccurrenceListComponent();
    }

    /**
     * Consulta ocurrencias del espacio.
     *
     * @param GetOccurrencesDTO $dto Petición.
     * @return void
     */
    public function run(GetOccurrencesDTO $dto): void {
        if (
            !$dto->isValid() ||
            $dto->spaceId === null ||
            $dto->idUser === null
        ) {
            $this->error = 'validation_error';
            $this->message = 'Los parámetros no son válidos.';
            OMiddleware::setStatusCode(400);
            return;
        }

        try {
            $this->list->list = $this->occurrence_service->getBySpace(
                $dto->spaceId,
                $dto->idUser,
                $dto->status
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

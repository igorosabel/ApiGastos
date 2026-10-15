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
    public int $total = 0;
    public int $page = 1;
    public int $page_size = 20;
    public int $total_pages = 0;
    public ?string $error = null;
    public ?string $message = null;

    /**
     * Inicializa el servicio y el listado.
     */
    public function __construct() {
        parent::__construct();
        $this->occurrence_service = inject(
            OccurrenceService::class
        );
        $this->list = new OccurrenceListComponent();
    }

    /**
     * Consulta ocurrencias filtradas y paginadas.
     *
     * @param GetOccurrencesDTO $dto Petición autenticada.
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
            $result = $this->occurrence_service->search(
                $dto->spaceId,
                $dto->idUser,
                $dto->status,
                $dto->periodFrom,
                $dto->periodTo,
                $dto->recurringId,
                $dto->page ?? 1,
                $dto->pageSize ?? 20
            );

            $this->list->list = $result['occurrences'];
            $this->total = $result['total'];
            $this->page = $result['page'];
            $this->page_size = $result['page_size'];
            $this->total_pages = $result['total_pages'];
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

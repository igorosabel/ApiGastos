<?php declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Module\Recurring\GenerateSpaceOccurrences;

use DomainException;
use InvalidArgumentException;
use Osumi\OsumiFramework\App\DTO\Recurring\GenerateSpaceOccurrencesDTO;
use Osumi\OsumiFramework\App\Service\Recurring\SpaceOccurrenceService;
use Osumi\OsumiFramework\Core\OComponent;
use Osumi\OsumiFramework\Core\OMiddleware;

class GenerateSpaceOccurrencesComponent extends OComponent {
    private SpaceOccurrenceService $space_occurrence_service;

    public bool $success = false;
    public int $created = 0;
    public int $existing = 0;
    public int $skipped = 0;
    public int $total = 0;
    public ?string $error = null;
    public ?string $message = null;

    /**
     * Inicializa el servicio.
     */
    public function __construct() {
        parent::__construct();

        $this->space_occurrence_service = inject(
            SpaceOccurrenceService::class
        );
    }

    /**
     * Genera un periodo para las plantillas activas del espacio.
     *
     * @param GenerateSpaceOccurrencesDTO $dto Datos de entrada.
     * @return void
     */
    public function run(GenerateSpaceOccurrencesDTO $dto): void {
        if (
            !$dto->isValid() ||
            $dto->spaceId === null ||
            $dto->period === null ||
            $dto->idUser === null
        ) {
            $this->error = 'validation_error';
            $this->message = 'Faltan datos obligatorios.';
            OMiddleware::setStatusCode(400);
            return;
        }

        try {
            $result = $this->space_occurrence_service->generate(
                $dto->spaceId,
                $dto->idUser,
                $dto->period
            );

            $this->created = $result['created'];
            $this->existing = $result['existing'];
            $this->skipped = $result['skipped'];
            $this->total = $result['total'];
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

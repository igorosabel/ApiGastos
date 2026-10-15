<?php declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Module\Recurring\GenerateOccurrence;

use DomainException;
use InvalidArgumentException;
use Osumi\OsumiFramework\App\Component\Model\Occurrence\OccurrenceComponent;
use Osumi\OsumiFramework\App\DTO\Recurring\GenerateOccurrenceDTO;
use Osumi\OsumiFramework\App\Service\Recurring\OccurrenceService;
use Osumi\OsumiFramework\Core\OComponent;
use Osumi\OsumiFramework\Core\OMiddleware;

class GenerateOccurrenceComponent extends OComponent {
    private OccurrenceService $occurrence_service;

    public bool $success = false;
    public bool $created = false;
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
     * Genera una mensualidad o devuelve la existente.
     *
     * @param GenerateOccurrenceDTO $dto Petición.
     * @return void
     */
    public function run(GenerateOccurrenceDTO $dto): void {
        if (
            !$dto->isValid() ||
            $dto->spaceId === null ||
            $dto->recurringId === null ||
            $dto->period === null ||
            $dto->idUser === null
        ) {
            $this->error = 'validation_error';
            $this->message = 'Faltan datos obligatorios.';
            OMiddleware::setStatusCode(400);
            return;
        }

        try {
            $result = $this->occurrence_service->generate(
                $dto->spaceId,
                $dto->idUser,
                $dto->recurringId,
                $dto->period
            );

            $this->occurrence = new OccurrenceComponent([
                'occurrence' => $result['occurrence']
            ]);

            $this->created = $result['created'];
            $this->success = true;

            if ($this->created) {
                OMiddleware::setStatusCode(201);
            }
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

<?php declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Module\Recurring\SetRecurringStatus;

use DomainException;
use InvalidArgumentException;
use Osumi\OsumiFramework\App\Component\Model\Recurring\RecurringComponent;
use Osumi\OsumiFramework\App\DTO\Recurring\SetRecurringStatusDTO;
use Osumi\OsumiFramework\App\Service\Recurring\RecurringService;
use Osumi\OsumiFramework\Core\OComponent;
use Osumi\OsumiFramework\Core\OMiddleware;

class SetRecurringStatusComponent extends OComponent {
    private RecurringService $recurring_service;

    public bool $success = false;
    public ?RecurringComponent $recurring = null;
    public ?string $error = null;
    public ?string $message = null;

    /**
     * Inicializa el servicio de recurrentes.
     */
    public function __construct() {
        parent::__construct();
        $this->recurring_service = inject(RecurringService::class);
    }

    /**
     * Activa o desactiva una plantilla.
     *
     * @param SetRecurringStatusDTO $dto Datos de entrada.
     * @return void
     */
    public function run(SetRecurringStatusDTO $dto): void {
        if (
            !$dto->isValid() ||
            $dto->spaceId === null ||
            $dto->recurringId === null ||
            $dto->active === null ||
            $dto->idUser === null
        ) {
            $this->error = 'validation_error';
            $this->message = 'Faltan datos obligatorios.';
            OMiddleware::setStatusCode(400);
            return;
        }

        try {
            $updated = $this->recurring_service->setActive(
                $dto->spaceId,
                $dto->idUser,
                $dto->recurringId,
                $dto->active
            );

            $this->recurring = new RecurringComponent([
                'recurring' => $updated
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
    }
}

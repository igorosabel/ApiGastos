<?php declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Module\Recurring\UpdateRecurring;

use DomainException;
use InvalidArgumentException;
use Osumi\OsumiFramework\App\Component\Model\Recurring\RecurringComponent;
use Osumi\OsumiFramework\App\DTO\Recurring\UpdateRecurringDTO;
use Osumi\OsumiFramework\App\Service\Recurring\RecurringService;
use Osumi\OsumiFramework\Core\OComponent;
use Osumi\OsumiFramework\Core\OMiddleware;

class UpdateRecurringComponent extends OComponent {
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
     * Edita una plantilla sin modificar sus ocurrencias.
     *
     * @param UpdateRecurringDTO $dto Datos de entrada.
     * @return void
     */
    public function run(UpdateRecurringDTO $dto): void {
        if (
            !$dto->isValid() ||
            $dto->spaceId === null ||
            $dto->recurringId === null ||
            $dto->categoryId === null ||
            $dto->type === null ||
            $dto->amount === null ||
            $dto->concept === null ||
            $dto->dayOfMonth === null ||
            $dto->startDate === null ||
            $dto->idUser === null
        ) {
            $this->error = 'validation_error';
            $this->message = 'Faltan datos obligatorios.';
            OMiddleware::setStatusCode(400);
            return;
        }

        try {
            $updated = $this->recurring_service->update(
                $dto->spaceId,
                $dto->idUser,
                $dto->recurringId,
                $dto->categoryId,
                $dto->type,
                $dto->amount,
                $dto->concept,
                $dto->dayOfMonth,
                $dto->startDate,
                $dto->endDate
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

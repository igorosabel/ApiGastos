<?php declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Module\Recurring\CreateRecurring;

use DomainException;
use InvalidArgumentException;
use Osumi\OsumiFramework\App\Component\Model\Recurring\RecurringComponent;
use Osumi\OsumiFramework\App\DTO\Recurring\CreateRecurringDTO;
use Osumi\OsumiFramework\App\Service\Recurring\RecurringService;
use Osumi\OsumiFramework\Core\OComponent;
use Osumi\OsumiFramework\Core\OMiddleware;

class CreateRecurringComponent extends OComponent {
    private RecurringService $recurring_service;

    public bool $success = false;
    public ?RecurringComponent $recurring = null;
    public ?string $error = null;
    public ?string $message = null;

    /**
     * Inicializa el servicio.
     */
    public function __construct() {
        parent::__construct();
        $this->recurring_service = inject(
            RecurringService::class
        );
    }

    /**
     * Registra una plantilla recurrente.
     *
     * @param CreateRecurringDTO $dto Datos.
     * @return void
     */
    public function run(CreateRecurringDTO $dto): void {
        if (
            !$dto->isValid() ||
            $dto->spaceId === null ||
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
            $created = $this->recurring_service->create(
                $dto->spaceId,
                $dto->idUser,
                $dto->categoryId,
                $dto->type,
                $dto->amount,
                $dto->concept,
                $dto->dayOfMonth,
                $dto->startDate,
                $dto->endDate
            );

            $this->recurring = new RecurringComponent([
                'recurring' => $created
            ]);
            $this->success = true;
            OMiddleware::setStatusCode(201);
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

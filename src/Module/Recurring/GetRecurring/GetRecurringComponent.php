<?php declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Module\Recurring\GetRecurring;

use DomainException;
use InvalidArgumentException;
use Osumi\OsumiFramework\App\Component\Model\RecurringList\RecurringListComponent;
use Osumi\OsumiFramework\App\DTO\Recurring\GetRecurringDTO;
use Osumi\OsumiFramework\App\Service\Recurring\RecurringService;
use Osumi\OsumiFramework\Core\OComponent;
use Osumi\OsumiFramework\Core\OMiddleware;

class GetRecurringComponent extends OComponent {
    private RecurringService $recurring_service;

    public bool $success = false;
    public ?RecurringListComponent $list = null;
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
        $this->list = new RecurringListComponent();
    }

    /**
     * Consulta las plantillas recurrentes del espacio.
     *
     * @param GetRecurringDTO $dto Datos.
     * @return void
     */
    public function run(GetRecurringDTO $dto): void {
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
            $this->list->list = $this->recurring_service->getBySpace(
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
    }
}

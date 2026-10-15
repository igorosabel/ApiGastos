<?php declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Module\Transaction\DeleteTransaction;

use DomainException;
use InvalidArgumentException;
use LogicException;
use Osumi\OsumiFramework\App\DTO\Transaction\DeleteTransactionDTO;
use Osumi\OsumiFramework\App\Service\Transaction\TransactionService;
use Osumi\OsumiFramework\Core\OComponent;
use Osumi\OsumiFramework\Core\OMiddleware;

class DeleteTransactionComponent extends OComponent {
    private TransactionService $transaction_service;

    public bool $success = false;
    public ?string $error = null;
    public ?string $message = null;

    /**
     * Inicializa el servicio.
     */
    public function __construct() {
        parent::__construct();

        $this->transaction_service = inject(
            TransactionService::class
        );
    }

    /**
     * Elimina un movimiento independiente.
     *
     * @param DeleteTransactionDTO $dto Datos de entrada.
     * @return void
     */
    public function run(DeleteTransactionDTO $dto): void {
        if (
            !$dto->isValid() ||
            $dto->spaceId === null ||
            $dto->transactionId === null ||
            $dto->idUser === null
        ) {
            $this->error = 'validation_error';
            $this->message = 'Faltan datos obligatorios.';
            OMiddleware::setStatusCode(400);
            return;
        }

        try {
            $this->transaction_service->delete(
                $dto->spaceId,
                $dto->idUser,
                $dto->transactionId
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
            $this->error = 'transaction_conflict';
            $this->message = $e->getMessage();
            OMiddleware::setStatusCode(409);
        }
    }
}

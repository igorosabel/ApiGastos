<?php declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Module\Transaction\UpdateTransaction;

use DomainException;
use InvalidArgumentException;
use LogicException;
use Osumi\OsumiFramework\App\Component\Model\Transaction\TransactionComponent;
use Osumi\OsumiFramework\App\DTO\Transaction\UpdateTransactionDTO;
use Osumi\OsumiFramework\App\Service\Transaction\TransactionService;
use Osumi\OsumiFramework\Core\OComponent;
use Osumi\OsumiFramework\Core\OMiddleware;

class UpdateTransactionComponent extends OComponent {
    private TransactionService $transaction_service;

    public bool $success = false;
    public ?TransactionComponent $transaction = null;
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
     * Edita un movimiento del espacio autorizado.
     *
     * @param UpdateTransactionDTO $dto Datos de entrada.
     * @return void
     */
    public function run(UpdateTransactionDTO $dto): void {
        if (
            !$dto->isValid() ||
            $dto->spaceId === null ||
            $dto->transactionId === null ||
            $dto->categoryId === null ||
            $dto->type === null ||
            $dto->amount === null ||
            $dto->date === null ||
            $dto->idUser === null
        ) {
            $this->error = 'validation_error';
            $this->message = 'Faltan datos obligatorios.';
            OMiddleware::setStatusCode(400);
            return;
        }

        try {
            $updated = $this->transaction_service->update(
                $dto->spaceId,
                $dto->idUser,
                $dto->transactionId,
                $dto->categoryId,
                $dto->type,
                $dto->amount,
                $dto->concept,
                $dto->date
            );

            $this->transaction = new TransactionComponent([
                'transaction' => $updated
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
            $this->error = 'transaction_conflict';
            $this->message = $e->getMessage();
            OMiddleware::setStatusCode(409);
        }
    }
}

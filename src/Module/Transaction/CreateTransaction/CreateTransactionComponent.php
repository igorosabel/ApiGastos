<?php declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Module\Transaction\CreateTransaction;

use DomainException;
use InvalidArgumentException;
use Osumi\OsumiFramework\App\Component\Model\Transaction\TransactionComponent;
use Osumi\OsumiFramework\App\DTO\Transaction\CreateTransactionDTO;
use Osumi\OsumiFramework\App\Service\Transaction\TransactionService;
use Osumi\OsumiFramework\Core\OComponent;
use Osumi\OsumiFramework\Core\OMiddleware;

class CreateTransactionComponent extends OComponent {
    private TransactionService $transaction_service;

    public bool $success = false;
    public ?TransactionComponent $transaction = null;
    public ?string $error = null;
    public ?string $message = null;

    /**
     * Inicializa el servicio de movimientos.
     */
    public function __construct() {
        parent::__construct();
        $this->transaction_service = inject(
            TransactionService::class
        );
    }

    /**
     * Crea un ingreso o gasto.
     *
     * @param CreateTransactionDTO $dto Datos de entrada.
     * @return void
     */
    public function run(CreateTransactionDTO $dto): void {
        if (
            !$dto->isValid() ||
            $dto->spaceId === null ||
            $dto->categoryId === null ||
            $dto->idUser === null ||
            $dto->type === null ||
            $dto->amount === null ||
            $dto->date === null
        ) {
            $this->error = 'validation_error';
            $this->message = 'Faltan datos obligatorios.';
            OMiddleware::setStatusCode(400);
            return;
        }

        try {
            $created = $this->transaction_service->create(
                $dto->spaceId,
                $dto->idUser,
                $dto->categoryId,
                $dto->type,
                $dto->amount,
                $dto->concept,
                $dto->date
            );

            $this->transaction = new TransactionComponent([
                'transaction' => $created
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

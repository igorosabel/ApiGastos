<?php declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Module\Transaction\GetTransactions;

use DomainException;
use InvalidArgumentException;
use Osumi\OsumiFramework\App\Component\Model\TransactionList\TransactionListComponent;
use Osumi\OsumiFramework\App\DTO\Transaction\GetTransactionsDTO;
use Osumi\OsumiFramework\App\Service\Transaction\TransactionService;
use Osumi\OsumiFramework\Core\OComponent;
use Osumi\OsumiFramework\Core\OMiddleware;

class GetTransactionsComponent extends OComponent {
    private TransactionService $transaction_service;

    public bool $success = false;
    public ?TransactionListComponent $list = null;
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
        $this->transaction_service = inject(
            TransactionService::class
        );
        $this->list = new TransactionListComponent();
    }

    /**
     * Consulta los movimientos filtrados y paginados.
     *
     * @param GetTransactionsDTO $dto Datos de entrada.
     * @return void
     */
    public function run(GetTransactionsDTO $dto): void {
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
            $result = $this->transaction_service->search(
                $dto->spaceId,
                $dto->idUser,
                $dto->dateFrom,
                $dto->dateTo,
                $dto->type,
                $dto->categoryId,
                $dto->page ?? 1,
                $dto->pageSize ?? 20
            );

            $this->list->list = $result['transactions'];
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

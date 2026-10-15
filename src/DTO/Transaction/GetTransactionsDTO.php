<?php declare(strict_types=1);

namespace Osumi\OsumiFramework\App\DTO\Transaction;

use Osumi\OsumiFramework\DTO\ODTO;
use Osumi\OsumiFramework\DTO\ODTOField;

class GetTransactionsDTO extends ODTO {
    #[ODTOField(required: true)]
    public ?int $spaceId = null;

    #[ODTOField]
    public ?string $dateFrom = null;

    #[ODTOField]
    public ?string $dateTo = null;

    #[ODTOField]
    public ?string $type = null;

    #[ODTOField]
    public ?int $categoryId = null;

    #[ODTOField]
    public ?int $page = null;

    #[ODTOField]
    public ?int $pageSize = null;

    #[ODTOField(
        required: true,
        middleware: 'Login',
        middlewareProperty: 'id'
    )]
    public ?int $idUser = null;
}

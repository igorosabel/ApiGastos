<?php declare(strict_types=1);

namespace Osumi\OsumiFramework\App\DTO\Transaction;

use Osumi\OsumiFramework\DTO\ODTO;
use Osumi\OsumiFramework\DTO\ODTOField;

class UpdateTransactionDTO extends ODTO {
    #[ODTOField(required: true)]
    public ?int $spaceId = null;

    #[ODTOField(required: true)]
    public ?int $transactionId = null;

    #[ODTOField(required: true)]
    public ?int $categoryId = null;

    #[ODTOField(required: true)]
    public ?string $type = null;

    #[ODTOField(required: true)]
    public ?string $amount = null;

    #[ODTOField]
    public ?string $concept = null;

    #[ODTOField(required: true)]
    public ?string $date = null;

    #[ODTOField(
        required: true,
        middleware: 'Login',
        middlewareProperty: 'id'
    )]
    public ?int $idUser = null;
}

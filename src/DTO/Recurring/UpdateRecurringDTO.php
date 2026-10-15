<?php declare(strict_types=1);

namespace Osumi\OsumiFramework\App\DTO\Recurring;

use Osumi\OsumiFramework\DTO\ODTO;
use Osumi\OsumiFramework\DTO\ODTOField;

class UpdateRecurringDTO extends ODTO {
    #[ODTOField(required: true)]
    public ?int $spaceId = null;

    #[ODTOField(required: true)]
    public ?int $recurringId = null;

    #[ODTOField(required: true)]
    public ?int $categoryId = null;

    #[ODTOField(required: true)]
    public ?string $type = null;

    #[ODTOField(required: true)]
    public ?string $amount = null;

    #[ODTOField(required: true)]
    public ?string $concept = null;

    #[ODTOField(required: true)]
    public ?int $dayOfMonth = null;

    #[ODTOField(required: true)]
    public ?string $startDate = null;

    #[ODTOField]
    public ?string $endDate = null;

    #[ODTOField(
        required: true,
        middleware: 'Login',
        middlewareProperty: 'id'
    )]
    public ?int $idUser = null;
}

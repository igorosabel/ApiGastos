<?php declare(strict_types=1);

namespace Osumi\OsumiFramework\App\DTO\Recurring;

use Osumi\OsumiFramework\DTO\ODTO;
use Osumi\OsumiFramework\DTO\ODTOField;

class GetOccurrencesDTO extends ODTO {
    #[ODTOField(required: true)]
    public ?int $spaceId = null;

    #[ODTOField]
    public ?string $status = null;

    #[ODTOField]
    public ?string $periodFrom = null;

    #[ODTOField]
    public ?string $periodTo = null;

    #[ODTOField]
    public ?int $recurringId = null;

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

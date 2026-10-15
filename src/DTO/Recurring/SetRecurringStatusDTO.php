<?php declare(strict_types=1);

namespace Osumi\OsumiFramework\App\DTO\Recurring;

use Osumi\OsumiFramework\DTO\ODTO;
use Osumi\OsumiFramework\DTO\ODTOField;

class SetRecurringStatusDTO extends ODTO {
    #[ODTOField(required: true)]
    public ?int $spaceId = null;

    #[ODTOField(required: true)]
    public ?int $recurringId = null;

    #[ODTOField(required: true)]
    public ?bool $active = null;

    #[ODTOField(
        required: true,
        middleware: 'Login',
        middlewareProperty: 'id'
    )]
    public ?int $idUser = null;
}

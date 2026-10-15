<?php declare(strict_types=1);

namespace Osumi\OsumiFramework\App\DTO\Recurring;

use Osumi\OsumiFramework\DTO\ODTO;
use Osumi\OsumiFramework\DTO\ODTOField;

class GetRecurringDTO extends ODTO {
    #[ODTOField(required: true)]
    public ?int $spaceId = null;

    #[ODTOField(
        required: true,
        middleware: 'Login',
        middlewareProperty: 'id'
    )]
    public ?int $idUser = null;
}

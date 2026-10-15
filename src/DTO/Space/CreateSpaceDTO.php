<?php declare(strict_types=1);

namespace Osumi\OsumiFramework\App\DTO\Space;

use Osumi\OsumiFramework\DTO\ODTO;
use Osumi\OsumiFramework\DTO\ODTOField;

class CreateSpaceDTO extends ODTO {
    #[ODTOField(required: true)]
    public ?string $name = null;

    #[ODTOField(
        required: true,
        middleware: 'Login',
        middlewareProperty: 'id'
    )]
    public ?int $idUser = null;
}

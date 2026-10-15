<?php declare(strict_types=1);

namespace Osumi\OsumiFramework\App\DTO;

use Osumi\OsumiFramework\DTO\ODTO;
use Osumi\OsumiFramework\DTO\ODTOField;

class RemoveSpaceMemberDTO extends ODTO {
    #[ODTOField(required: true)]
    public ?int $spaceId = null;

    #[ODTOField(required: true)]
    public ?int $userId = null;

    #[ODTOField(
        required: true,
        middleware: 'Login',
        middlewareProperty: 'id'
    )]
    public ?int $idUser = null;
}

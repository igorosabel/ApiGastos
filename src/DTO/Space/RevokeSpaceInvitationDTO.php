<?php declare(strict_types=1);

namespace Osumi\OsumiFramework\App\DTO\Space;

use Osumi\OsumiFramework\DTO\ODTO;
use Osumi\OsumiFramework\DTO\ODTOField;

class RevokeSpaceInvitationDTO extends ODTO {
    #[ODTOField(required: true)]
    public ?int $invitationId = null;

    #[ODTOField(
        required: true,
        middleware: 'Login',
        middlewareProperty: 'id'
    )]
    public ?int $idUser = null;
}

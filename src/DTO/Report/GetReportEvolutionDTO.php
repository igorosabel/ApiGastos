<?php declare(strict_types=1);

namespace Osumi\OsumiFramework\App\DTO\Report;

use Osumi\OsumiFramework\DTO\ODTO;
use Osumi\OsumiFramework\DTO\ODTOField;

class GetReportEvolutionDTO extends ODTO {
    #[ODTOField(required: true)]
    public ?int $spaceId = null;

    #[ODTOField(required: true)]
    public ?string $monthFrom = null;

    #[ODTOField(required: true)]
    public ?string $monthTo = null;

    #[ODTOField(
        required: true,
        middleware: 'Login',
        middlewareProperty: 'id'
    )]
    public ?int $idUser = null;
}

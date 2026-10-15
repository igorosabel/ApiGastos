<?php declare(strict_types=1);

namespace Osumi\OsumiFramework\App\DTO\Category;

use Osumi\OsumiFramework\DTO\ODTO;
use Osumi\OsumiFramework\DTO\ODTOField;

class CreateCategoryDTO extends ODTO {
    #[ODTOField(required: true)]
    public ?int $spaceId = null;

    #[ODTOField(required: true)]
    public ?string $name = null;

    #[ODTOField(required: true)]
    public ?string $defaultType = null;

    #[ODTOField]
    public ?int $parentId = null;

    #[ODTOField]
    public ?int $sortOrder = null;

    #[ODTOField(
        required: true,
        middleware: 'Login',
        middlewareProperty: 'id'
    )]
    public ?int $idUser = null;
}

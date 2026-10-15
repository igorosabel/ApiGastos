<?php declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Component\Model\SpaceList;

use Osumi\OsumiFramework\Core\OComponent;

class SpaceListComponent extends OComponent {
    public array $list = [];
    public ?int $user_id = null;
}

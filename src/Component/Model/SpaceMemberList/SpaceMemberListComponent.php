<?php declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Component\Model\SpaceMemberList;

use Osumi\OsumiFramework\Core\OComponent;

class SpaceMemberListComponent extends OComponent {
    public array $list = [];
    public ?int $owner_id = null;
}

<?php declare(strict_types=1);

use Osumi\OsumiFramework\App\Component\Model\SpaceMember\SpaceMemberComponent;

foreach ($list as $i => $member) {
    $component = new SpaceMemberComponent([
        'member' => $member,
        'owner_id' => $owner_id
    ]);

    echo strval($component);

    if ($i < count($list) - 1) {
        echo ',';
    }
}

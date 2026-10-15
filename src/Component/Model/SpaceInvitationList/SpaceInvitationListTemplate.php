<?php declare(strict_types=1);

use Osumi\OsumiFramework\App\Component\Model\SpaceInvitation\SpaceInvitationComponent;

foreach ($list as $i => $invitation) {
    $component = new SpaceInvitationComponent([
        'invitation' => $invitation
    ]);

    echo strval($component);

    if ($i < count($list) - 1) {
        echo ',';
    }
}

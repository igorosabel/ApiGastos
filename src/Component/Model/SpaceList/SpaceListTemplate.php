<?php declare(strict_types=1);

use Osumi\OsumiFramework\App\Component\Model\Space\SpaceComponent;

foreach ($list as $i => $space) {
    $component = new SpaceComponent([
        'space' => $space,
        'user_id' => $user_id
    ]);

    echo strval($component);

    if ($i < count($list) - 1) {
        echo ',';
    }
}

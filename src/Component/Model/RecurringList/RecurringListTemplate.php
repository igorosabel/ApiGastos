<?php declare(strict_types=1);

use Osumi\OsumiFramework\App\Component\Model\Recurring\RecurringComponent;

foreach ($list as $i => $recurring) {
    $component = new RecurringComponent([
        'recurring' => $recurring
    ]);

    echo strval($component);

    if ($i < count($list) - 1) {
        echo ',';
    }
}

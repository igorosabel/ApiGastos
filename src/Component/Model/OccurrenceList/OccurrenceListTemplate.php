<?php declare(strict_types=1);

use Osumi\OsumiFramework\App\Component\Model\Occurrence\OccurrenceComponent;

foreach ($list as $i => $occurrence) {
    $component = new OccurrenceComponent([
        'occurrence' => $occurrence
    ]);

    echo strval($component);

    if ($i < count($list) - 1) {
        echo ',';
    }
}

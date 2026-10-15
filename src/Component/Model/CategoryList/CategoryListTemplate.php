<?php declare(strict_types=1);

use Osumi\OsumiFramework\App\Component\Model\Category\CategoryComponent;

foreach ($list as $i => $category) {
    $component = new CategoryComponent([
        'category' => $category
    ]);

    echo strval($component);

    if ($i < count($list) - 1) {
        echo ',';
    }
}

<?php declare(strict_types=1);

use Osumi\OsumiFramework\App\Component\Model\CategoryReport\CategoryReportComponent;

foreach ($list as $i => $category) {
    echo strval(new CategoryReportComponent($category));

    if ($i < count($list) - 1) {
        echo ',';
    }
}

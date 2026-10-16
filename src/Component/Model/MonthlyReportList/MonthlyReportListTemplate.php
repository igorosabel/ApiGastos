<?php declare(strict_types=1);

use Osumi\OsumiFramework\App\Component\Model\MonthlyReport\MonthlyReportComponent;

foreach ($list as $i => $month) {
    echo strval(new MonthlyReportComponent($month));

    if ($i < count($list) - 1) {
        echo ',';
    }
}

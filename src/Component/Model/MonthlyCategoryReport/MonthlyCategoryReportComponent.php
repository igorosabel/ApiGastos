<?php declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Component\Model\MonthlyCategoryReport;

use Osumi\OsumiFramework\App\Component\Model\CategoryReportList\CategoryReportListComponent;
use Osumi\OsumiFramework\Core\OComponent;

class MonthlyCategoryReportComponent extends OComponent {
    public int $space_id = 0;
    public string $month = '';
    public ?CategoryReportListComponent $categories = null;
}

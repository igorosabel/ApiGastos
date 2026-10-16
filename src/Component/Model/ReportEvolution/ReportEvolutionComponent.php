<?php declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Component\Model\ReportEvolution;

use Osumi\OsumiFramework\App\Component\Model\MonthlyReportList\MonthlyReportListComponent;
use Osumi\OsumiFramework\Core\OComponent;

class ReportEvolutionComponent extends OComponent {
    public int $space_id = 0;
    public string $month_from = '';
    public string $month_to = '';
    public ?MonthlyReportListComponent $months = null;
}

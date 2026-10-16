<?php declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Component\Model\MonthlyForecast;

use Osumi\OsumiFramework\App\Component\Model\MonthlyReport\MonthlyReportComponent;
use Osumi\OsumiFramework\Core\OComponent;

class MonthlyForecastComponent extends OComponent {
    public int $space_id = 0;
    public string $month = '';

    public ?MonthlyReportComponent $actual = null;

    public string $pending_income = '0.00';
    public string $pending_expense = '0.00';
    public string $pending_balance = '0.00';
    public int $pending_income_count = 0;
    public int $pending_expense_count = 0;
    public int $pending_occurrence_count = 0;

    public string $projected_income = '0.00';
    public string $projected_expense = '0.00';
    public string $projected_balance = '0.00';
}

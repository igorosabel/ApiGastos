<?php declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Component\Model\MonthlyReport;

use Osumi\OsumiFramework\Core\OComponent;

class MonthlyReportComponent extends OComponent {
    public int $space_id = 0;
    public string $month = '';
    public string $income = '0.00';
    public string $expense = '0.00';
    public string $balance = '0.00';
    public int $income_count = 0;
    public int $expense_count = 0;
    public int $transaction_count = 0;
}

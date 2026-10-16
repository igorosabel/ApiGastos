<?php declare(strict_types=1);

use Osumi\OsumiFramework\App\Component\Model\MonthlyForecast\MonthlyForecastComponent;
use Osumi\OsumiFramework\App\Component\Model\MonthlyReport\MonthlyReportComponent;

foreach ($list as $i => $month) {
    $component = new MonthlyForecastComponent([
        'space_id' => $month['space_id'],
        'month' => $month['month'],
        'actual' => new MonthlyReportComponent(
            $month['actual']
        ),
        'pending_income' => $month['pending_income'],
        'pending_expense' => $month['pending_expense'],
        'pending_balance' => $month['pending_balance'],
        'pending_income_count' => $month['pending_income_count'],
        'pending_expense_count' => $month['pending_expense_count'],
        'pending_occurrence_count' => $month['pending_occurrence_count'],
        'projected_income' => $month['projected_income'],
        'projected_expense' => $month['projected_expense'],
        'projected_balance' => $month['projected_balance']
    ]);

    echo strval($component);

    if ($i < count($list) - 1) {
        echo ',';
    }
}

<?php declare(strict_types=1);

namespace Osumi\OsumiFramework\Routes;

use Osumi\OsumiFramework\Routing\ORoute;
use Osumi\OsumiFramework\Core\OMiddleware;
use Osumi\OsumiFramework\App\Middleware\LoginMiddleware;
use Osumi\OsumiFramework\App\Module\Report\GetMonthlyReport\GetMonthlyReportComponent;
use Osumi\OsumiFramework\App\Module\Report\GetMonthlyCategoryReport\GetMonthlyCategoryReportComponent;
use Osumi\OsumiFramework\App\Module\Report\GetReportEvolution\GetReportEvolutionComponent;

ORoute::prefix('/reports', function(): void {
    ORoute::get('/monthly', GetMonthlyReportComponent::class, [
        OMiddleware::PHASE_BEFORE => [
            LoginMiddleware::class
        ]
    ]);

    ORoute::get(
        '/monthly/categories',
        GetMonthlyCategoryReportComponent::class,
        [
            OMiddleware::PHASE_BEFORE => [
                LoginMiddleware::class
            ]
        ]
    );

    ORoute::get('/evolution', GetReportEvolutionComponent::class, [
        OMiddleware::PHASE_BEFORE => [
            LoginMiddleware::class
        ]
    ]);
});

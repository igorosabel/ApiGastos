<?php declare(strict_types=1);

namespace Osumi\OsumiFramework\Routes;

use Osumi\OsumiFramework\Routing\ORoute;
use Osumi\OsumiFramework\Core\OMiddleware;
use Osumi\OsumiFramework\App\Middleware\LoginMiddleware;
use Osumi\OsumiFramework\App\Module\Report\GetMonthlyReport\GetMonthlyReportComponent;

ORoute::prefix('/reports', function(): void {
    ORoute::get('/monthly', GetMonthlyReportComponent::class, [
        OMiddleware::PHASE_BEFORE => [
            LoginMiddleware::class
        ]
    ]);
});

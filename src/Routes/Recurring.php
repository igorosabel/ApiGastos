<?php declare(strict_types=1);

namespace Osumi\OsumiFramework\Routes;

use Osumi\OsumiFramework\Routing\ORoute;
use Osumi\OsumiFramework\Core\OMiddleware;
use Osumi\OsumiFramework\App\Middleware\LoginMiddleware;
use Osumi\OsumiFramework\App\Module\Recurring\CreateRecurring\CreateRecurringComponent;
use Osumi\OsumiFramework\App\Module\Recurring\GetRecurring\GetRecurringComponent;

ORoute::prefix('/recurring', function(): void {
    ORoute::get('', GetRecurringComponent::class, [
        OMiddleware::PHASE_BEFORE => [
            LoginMiddleware::class
        ]
    ]);

    ORoute::post('', CreateRecurringComponent::class, [
        OMiddleware::PHASE_BEFORE => [
            LoginMiddleware::class
        ]
    ]);
});

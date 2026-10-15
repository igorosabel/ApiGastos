<?php declare(strict_types=1);

namespace Osumi\OsumiFramework\Routes;

use Osumi\OsumiFramework\Routing\ORoute;
use Osumi\OsumiFramework\Core\OMiddleware;
use Osumi\OsumiFramework\App\Middleware\LoginMiddleware;
use Osumi\OsumiFramework\App\Module\Recurring\CreateRecurring\CreateRecurringComponent;
use Osumi\OsumiFramework\App\Module\Recurring\GetRecurring\GetRecurringComponent;
use Osumi\OsumiFramework\App\Module\Recurring\UpdateRecurring\UpdateRecurringComponent;
use Osumi\OsumiFramework\App\Module\Recurring\SetRecurringStatus\SetRecurringStatusComponent;
use Osumi\OsumiFramework\App\Module\Recurring\GenerateOccurrence\GenerateOccurrenceComponent;

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

    ORoute::post('/update', UpdateRecurringComponent::class, [
        OMiddleware::PHASE_BEFORE => [
            LoginMiddleware::class
        ]
    ]);

    ORoute::post('/status', SetRecurringStatusComponent::class, [
        OMiddleware::PHASE_BEFORE => [
            LoginMiddleware::class
        ]
    ]);

    ORoute::post('/generate', GenerateOccurrenceComponent::class, [
        OMiddleware::PHASE_BEFORE => [
            LoginMiddleware::class
        ]
    ]);
});

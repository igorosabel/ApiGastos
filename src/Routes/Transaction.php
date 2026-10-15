<?php declare(strict_types=1);

namespace Osumi\OsumiFramework\Routes;

use Osumi\OsumiFramework\Routing\ORoute;
use Osumi\OsumiFramework\Core\OMiddleware;
use Osumi\OsumiFramework\App\Middleware\LoginMiddleware;
use Osumi\OsumiFramework\App\Module\Transaction\CreateTransaction\CreateTransactionComponent;
use Osumi\OsumiFramework\App\Module\Transaction\GetTransactions\GetTransactionsComponent;
use Osumi\OsumiFramework\App\Module\Transaction\UpdateTransaction\UpdateTransactionComponent;

ORoute::prefix('/transactions', function(): void {
    ORoute::get('', GetTransactionsComponent::class, [
        OMiddleware::PHASE_BEFORE => [
            LoginMiddleware::class
        ]
    ]);

    ORoute::post('', CreateTransactionComponent::class, [
        OMiddleware::PHASE_BEFORE => [
            LoginMiddleware::class
        ]
    ]);

    ORoute::post('/update', UpdateTransactionComponent::class, [
        OMiddleware::PHASE_BEFORE => [
            LoginMiddleware::class
        ]
    ]);
});

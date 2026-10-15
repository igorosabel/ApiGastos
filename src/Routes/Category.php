<?php declare(strict_types=1);

namespace Osumi\OsumiFramework\Routes;

use Osumi\OsumiFramework\Routing\ORoute;
use Osumi\OsumiFramework\Core\OMiddleware;
use Osumi\OsumiFramework\App\Middleware\LoginMiddleware;
use Osumi\OsumiFramework\App\Module\Category\GetCategories\GetCategoriesComponent;
use Osumi\OsumiFramework\App\Module\Category\CreateCategory\CreateCategoryComponent;
use Osumi\OsumiFramework\App\Module\Category\UpdateCategory\UpdateCategoryComponent;
use Osumi\OsumiFramework\App\Module\Category\DeleteCategory\DeleteCategoryComponent;

ORoute::prefix('/categories', function(): void {
    ORoute::get('', GetCategoriesComponent::class, [
        OMiddleware::PHASE_BEFORE => [
            LoginMiddleware::class
        ]
    ]);

    ORoute::post('', CreateCategoryComponent::class, [
        OMiddleware::PHASE_BEFORE => [
            LoginMiddleware::class
        ]
    ]);

    ORoute::post('/update', UpdateCategoryComponent::class, [
        OMiddleware::PHASE_BEFORE => [
            LoginMiddleware::class
        ]
    ]);

    ORoute::post('/delete', DeleteCategoryComponent::class, [
        OMiddleware::PHASE_BEFORE => [
            LoginMiddleware::class
        ]
    ]);
});

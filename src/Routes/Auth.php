<?php declare(strict_types=1);

namespace Osumi\OsumiFramework\Routes;

use Osumi\OsumiFramework\Routing\ORoute;
use Osumi\OsumiFramework\Core\OMiddleware;
use Osumi\OsumiFramework\App\Middleware\LoginMiddleware;
use Osumi\OsumiFramework\App\Module\Auth\Register\RegisterComponent;
use Osumi\OsumiFramework\App\Module\Auth\Login\LoginComponent;
use Osumi\OsumiFramework\App\Module\Auth\Me\MeComponent;

ORoute::prefix('/auth', function(): void {
  ORoute::post('/register', RegisterComponent::class);
  ORoute::post('/login',    LoginComponent::class);
  ORoute::get('/me', MeComponent::class, [
    OMiddleware::PHASE_BEFORE => [
        LoginMiddleware::class
    ]
  ]);
});

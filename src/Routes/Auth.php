<?php declare(strict_types=1);

namespace Osumi\OsumiFramework\Routes;

use Osumi\OsumiFramework\Routing\ORoute;
use Osumi\OsumiFramework\App\Module\Auth\Register\RegisterComponent;
use Osumi\OsumiFramework\App\Module\Auth\Login\LoginComponent;

ORoute::prefix('/auth', function(): void {
  ORoute::post('/register', RegisterComponent::class);
  ORoute::post('/login',    LoginComponent::class);
});

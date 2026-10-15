<?php declare(strict_types=1);

namespace Osumi\OsumiFramework\Routes;

use Osumi\OsumiFramework\App\Module\Auth\Register\RegisterComponent;
use Osumi\OsumiFramework\Routing\ORoute;

ORoute::post('/auth/register', RegisterComponent::class);

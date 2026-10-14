<?php declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Module\Home\Index;

use Osumi\OsumiFramework\Core\OComponent;
use Osumi\OsumiFramework\Web\ORequest;
use Osumi\OsumiFramework\Routing\OUrl;

class IndexComponent extends OComponent {
	/**
	 * Raíz del sitio, redirige al usuario a la aplicación
	 *
	 * @return void
	 */
	public function run(): void {
		OUrl::goToUrl('https://gastos.osumi.es');
	}
}

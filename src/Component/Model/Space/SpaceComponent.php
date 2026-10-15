<?php declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Component\Model\Space;

use Osumi\OsumiFramework\App\Model\Space;
use Osumi\OsumiFramework\Core\OComponent;

class SpaceComponent extends OComponent {
    public ?Space $space = null;
    public ?int $user_id = null;
    public bool $is_owner = false;

    /**
     * Prepara los datos de presentación del espacio.
     */
    public function run(): void {
        $this->is_owner = $this->space !== null
            && $this->space->owner_id === $this->user_id;
    }
}

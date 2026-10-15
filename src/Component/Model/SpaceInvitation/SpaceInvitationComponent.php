<?php declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Component\Model\SpaceInvitation;

use Osumi\OsumiFramework\App\Model\SpaceInvitation;
use Osumi\OsumiFramework\Core\OComponent;

class SpaceInvitationComponent extends OComponent {
    public ?SpaceInvitation $invitation = null;
    public string $status = 'pending';

    /**
     * Calcula el estado de la invitación.
     *
     * @return void
     */
    public function run(): void {
        if ($this->invitation === null) {
            return;
        }

        if ($this->invitation->accepted_at !== null) {
            $this->status = 'accepted';
        }
        elseif ($this->invitation->revoked_at !== null) {
            $this->status = 'revoked';
        }
        elseif (
            $this->invitation->expires_at !== null &&
            strtotime($this->invitation->expires_at) <= time()
        ) {
            $this->status = 'expired';
        }
    }
}

<?php declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Component\Model\SpaceMember;

use Osumi\OsumiFramework\App\Model\SpaceMember;
use Osumi\OsumiFramework\App\Model\User;
use Osumi\OsumiFramework\Core\OComponent;

class SpaceMemberComponent extends OComponent {
    public ?SpaceMember $member = null;
    public ?int $owner_id = null;
    public ?User $user = null;
    public bool $is_owner = false;

    /**
     * Prepara la representación del miembro.
     */
    public function run(): void {
        if ($this->member === null) {
            return;
        }

        $this->user = User::findOne([
            'id' => $this->member->id_user
        ]);

        $this->is_owner =
            $this->member->id_user === $this->owner_id;
    }
}

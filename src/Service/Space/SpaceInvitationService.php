<?php declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Service\Space;

use DomainException;
use InvalidArgumentException;
use LogicException;
use Osumi\OsumiFramework\App\Model\Space;
use Osumi\OsumiFramework\App\Model\SpaceInvitation;
use Osumi\OsumiFramework\App\Model\SpaceMember;
use Osumi\OsumiFramework\App\Model\User;
use Osumi\OsumiFramework\Core\OService;
use RuntimeException;

class SpaceInvitationService extends OService {
    /**
     * Crea una invitación para un espacio compartido.
     *
     * @param int $space_id Identificador del espacio.
     * @param int $user_id Identificador del usuario autenticado.
     * @param string $email Correo al que va dirigida.
     * @return array{invitation: SpaceInvitation, token: string}
     */
    public function create(
        int $space_id,
        int $user_id,
        string $email
    ): array {
        if ($space_id <= 0 || $user_id <= 0) {
            throw new InvalidArgumentException(
                'Los identificadores no son válidos.'
            );
        }

        $email = mb_strtolower(trim($email));

        if (
            mb_strlen($email) > 254 ||
            !filter_var($email, FILTER_VALIDATE_EMAIL)
        ) {
            throw new InvalidArgumentException(
                'El correo electrónico no es válido.'
            );
        }

        $space = Space::findOne(['id' => $space_id]);

        if (
            $space === null ||
            $space->type !== 'shared' ||
            $space->owner_id !== $user_id
        ) {
            throw new DomainException(
                'No tienes permiso para invitar a este espacio.'
            );
        }

        $users = User::where(['email' => $email]);

        foreach ($users as $user) {
            if ($user->id === null) {
                continue;
            }

            $member = SpaceMember::findOne([
                'id_space' => $space_id,
                'id_user' => $user->id
            ]);

            if ($member !== null) {
                throw new LogicException(
                    'Este usuario ya pertenece al espacio.'
                );
            }
        }

        $invitations = SpaceInvitation::where([
            'id_space' => $space_id,
            'email' => $email
        ]);

        $now = time();

        foreach ($invitations as $invitation) {
            if (
                $invitation->accepted_at === null &&
                $invitation->revoked_at === null &&
                $invitation->expires_at !== null &&
                strtotime($invitation->expires_at) > $now
            ) {
                throw new LogicException(
                    'Ya existe una invitación pendiente para este correo.'
                );
            }
        }

        $token = bin2hex(random_bytes(32));

        $invitation = new SpaceInvitation();
        $invitation->id_space = $space_id;
        $invitation->email = $email;
        $invitation->token_hash = hash('sha256', $token);
        $invitation->invited_by = $user_id;
        $invitation->expires_at = date(
            'Y-m-d H:i:s',
            $now + (7 * 24 * 60 * 60)
        );
        $invitation->accepted_at = null;
        $invitation->revoked_at = null;

        if (!$invitation->save() || $invitation->id === null) {
            throw new RuntimeException(
                'No se ha podido crear la invitación.'
            );
        }

        return [
            'invitation' => $invitation,
            'token' => $token
        ];
    }
}

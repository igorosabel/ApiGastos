<?php declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Service\Space;

use DomainException;
use InvalidArgumentException;
use Osumi\OsumiFramework\App\Model\Space;
use Osumi\OsumiFramework\App\Model\SpaceMember;
use Osumi\OsumiFramework\Core\OService;

class SpaceMemberService extends OService {
    /**
     * Obtiene los miembros de un espacio compartido autorizado.
     *
     * @param int $space_id Identificador del espacio.
     * @param int $user_id Identificador del propietario.
     * @return list<SpaceMember> Miembros del espacio.
     */
    public function getMembers(int $space_id, int $user_id): array {
        if ($space_id <= 0 || $user_id <= 0) {
            throw new InvalidArgumentException(
                'Los identificadores no son válidos.'
            );
        }

        $space = Space::findOne(['id' => $space_id]);

        if (
            $space === null ||
            $space->type !== 'shared' ||
            $space->owner_id !== $user_id
        ) {
            throw new DomainException(
                'No tienes permiso para consultar estos miembros.'
            );
        }

        return SpaceMember::where(
            ['id_space' => $space_id],
            ['order_by' => 'joined_at#ASC']
        );
    }
}

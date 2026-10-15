<?php declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Service\Space;

use InvalidArgumentException;
use Osumi\OsumiFramework\App\Model\Space;
use Osumi\OsumiFramework\App\Model\SpaceMember;
use Osumi\OsumiFramework\Core\OService;
use Osumi\OsumiFramework\ORM\ODB;
use PDO;
use RuntimeException;
use Throwable;

class SpaceService extends OService {
    /**
     * Obtiene los espacios de los que es miembro un usuario.
     *
     * @param int $user_id Identificador del usuario.
     * @return list<Space> Espacios accesibles.
     */
    public function getUserSpaces(int $user_id): array {
        if ($user_id <= 0) {
            throw new InvalidArgumentException(
                'El identificador del usuario no es válido.'
            );
        }

        $memberships = SpaceMember::where([
            'id_user' => $user_id,
            'left_at' => null
        ]);

        $spaces = [];

        foreach ($memberships as $membership) {
            $space = Space::findOne([
                'id' => $membership->id_space
            ]);

            if ($space !== null) {
                $spaces[] = $space;
            }
        }

        usort(
            $spaces,
            static function(Space $a, Space $b): int {
                return [
                    $a->type,
                    $a->name,
                    $a->id
                ] <=> [
                    $b->type,
                    $b->name,
                    $b->id
                ];
            }
        );

        return $spaces;
    }

    /**
     * Crea un espacio compartido y su membresía inicial.
     *
     * @param int $user_id Identificador del propietario.
     * @param string $name Nombre propuesto.
     * @return Space Espacio creado.
     */
    public function createSharedSpace(int $user_id, string $name): Space {
        if ($user_id <= 0) {
            throw new InvalidArgumentException(
                'El identificador del usuario no es válido.'
            );
        }

        $name = trim($name);

        if ($name === '' || mb_strlen($name) > 100) {
            throw new InvalidArgumentException(
                'El nombre del espacio debe tener entre 1 y 100 caracteres.'
            );
        }

        $db = ODB::getInstance();

        if (!$db->beginTransaction()) {
            throw new RuntimeException(
                'No se ha podido iniciar la transacción.'
            );
        }

        try {
            $space = new Space();
            $space->name = $name;
            $space->type = 'shared';
            $space->owner_id = $user_id;
            $space->personal_owner_id = null;

            if (!$space->save() || $space->id === null) {
                throw new RuntimeException(
                    'No se ha podido crear el espacio.'
                );
            }

            $member = new SpaceMember();
            $member->id_space = $space->id;
            $member->id_user = $user_id;

            if (!$member->save()) {
                throw new RuntimeException(
                    'No se ha podido añadir el propietario al espacio.'
                );
            }

            if (!$db->commit()) {
                throw new RuntimeException(
                    'No se ha podido confirmar la creación del espacio.'
                );
            }

            return $space;
        }
        catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            throw $e;
        }
    }
}

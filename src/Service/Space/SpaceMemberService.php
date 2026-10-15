<?php declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Service\Space;

use DomainException;
use InvalidArgumentException;
use LogicException;
use PDO;
use RuntimeException;
use Throwable;
use Osumi\OsumiFramework\ORM\ODB;
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
            [
                'id_space' => $space_id,
                'left_at' => null
            ],
            ['order_by' => 'joined_at#ASC']
        );
    }

    /**
     * Permite a un miembro abandonar voluntariamente un espacio.
     *
     * @param int $space_id Espacio compartido.
     * @param int $user_id Usuario autenticado.
     */
    public function leave(int $space_id, int $user_id): void {
        $this->deactivate(
            $space_id,
            $user_id,
            $user_id,
            false
        );
    }

    /**
     * Permite al propietario expulsar a otro miembro.
     *
     * @param int $space_id Espacio compartido.
     * @param int $owner_id Propietario autenticado.
     * @param int $member_id Usuario que será expulsado.
     */
    public function remove(
        int $space_id,
        int $owner_id,
        int $member_id
    ): void {
        $this->deactivate(
            $space_id,
            $owner_id,
            $member_id,
            true
        );
    }

    /**
     * Marca una membresía como inactiva sin borrar su historial.
     *
     * @param int $space_id Espacio compartido.
     * @param int $actor_id Usuario que solicita la operación.
     * @param int $member_id Usuario afectado.
     * @param bool $by_owner Indica si es una expulsión.
     */
    private function deactivate(
        int $space_id,
        int $actor_id,
        int $member_id,
        bool $by_owner
    ): void {
        if (
            $space_id <= 0 ||
            $actor_id <= 0 ||
            $member_id <= 0
        ) {
            throw new InvalidArgumentException(
                'Los identificadores no son válidos.'
            );
        }

        $db = ODB::getInstance();

        if (!$db->beginTransaction()) {
            throw new RuntimeException(
                'No se ha podido iniciar la transacción.'
            );
        }

        try {
            // Bloqueo compartido por las operaciones que
            // modifican la pertenencia a este espacio.
            $stmt = $db->prepare(
                'SELECT type, owner_id
                 FROM `space`
                 WHERE id = :id
                 FOR UPDATE'
            );
            $stmt->execute(['id' => $space_id]);
            $space = $stmt->fetch(PDO::FETCH_ASSOC);

            if (
                !is_array($space) ||
                $space['type'] !== 'shared'
            ) {
                throw new DomainException(
                    'El espacio no permite esta operación.'
                );
            }

            $owner_id = (int) $space['owner_id'];

            if ($by_owner && $actor_id !== $owner_id) {
                throw new DomainException(
                    'Solo el propietario puede expulsar miembros.'
                );
            }

            if (!$by_owner && $actor_id !== $member_id) {
                throw new DomainException(
                    'No puedes abandonar el espacio por otro usuario.'
                );
            }

            if ($member_id === $owner_id) {
                throw new DomainException(
                    'El propietario no puede abandonar ni ser expulsado.'
                );
            }

            $stmt = $db->prepare(
                'UPDATE `space_member`
                 SET left_at = NOW()
                 WHERE id_space = :space_id
                   AND id_user = :member_id
                   AND left_at IS NULL'
            );
            $stmt->execute([
                'space_id' => $space_id,
                'member_id' => $member_id
            ]);

            if ($stmt->rowCount() !== 1) {
                throw new LogicException(
                    'El usuario no es miembro activo del espacio.'
                );
            }

            if (!$db->commit()) {
                throw new RuntimeException(
                    'No se ha podido confirmar la baja.'
                );
            }
        }
        catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            throw $e;
        }
    }
}

<?php declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Service\Space;

use RuntimeException;
use PDO;
use Throwable;
use DomainException;
use InvalidArgumentException;
use LogicException;
use Osumi\OsumiFramework\ORM\ODB;
use Osumi\OsumiFramework\App\Model\Space;
use Osumi\OsumiFramework\App\Model\SpaceInvitation;
use Osumi\OsumiFramework\App\Model\SpaceMember;
use Osumi\OsumiFramework\App\Model\User;
use Osumi\OsumiFramework\Core\OService;

class SpaceInvitationService extends OService {
  /**
    * Crea una invitación para un espacio compartido.
    *
    * Serializa las creaciones de un mismo espacio mediante
    * un bloqueo de fila para evitar invitaciones duplicadas.
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

       $db = ODB::getInstance();

       if (!$db->beginTransaction()) {
           throw new RuntimeException(
               'No se ha podido iniciar la transacción.'
           );
       }

       try {
           // Todas las creaciones del mismo espacio adquieren
           // este bloqueo antes de comprobar duplicados.
           $stmt = $db->prepare(
               'SELECT id, type, owner_id
                FROM `space`
                WHERE id = :space_id
                FOR UPDATE'
           );

           if (
               $stmt === false ||
               !$stmt->execute(['space_id' => $space_id])
           ) {
               throw new RuntimeException(
                   'No se ha podido consultar el espacio.'
               );
           }

           $space = $stmt->fetch(PDO::FETCH_ASSOC);

           if (
               !is_array($space) ||
               $space['type'] !== 'shared' ||
               (int) $space['owner_id'] !== $user_id
           ) {
               throw new DomainException(
                   'No tienes permiso para invitar a este espacio.'
               );
           }

           // Comprobamos si el destinatario ya es miembro.
           $stmt = $db->prepare(
               'SELECT sm.id
                FROM `space_member` sm
                INNER JOIN `user` u ON u.id = sm.id_user
                WHERE sm.id_space = :space_id
                  AND u.email = :email
                  AND sm.left_at IS NULL
                LIMIT 1'
           );
           $stmt->execute([
               'space_id' => $space_id,
               'email' => $email
           ]);

           if ($stmt->fetch(PDO::FETCH_ASSOC) !== false) {
               throw new LogicException(
                   'Este usuario ya pertenece al espacio.'
               );
           }

           // Consulta directa y actual dentro de la transacción.
           // Evita reutilizar resultados cacheados del ORM.
           $stmt = $db->prepare(
               'SELECT id
                FROM `space_invitation`
                WHERE id_space = :space_id
                  AND email = :email
                  AND accepted_at IS NULL
                  AND revoked_at IS NULL
                  AND expires_at > NOW()
                LIMIT 1'
           );

           if (
               $stmt === false ||
               !$stmt->execute([
                   'space_id' => $space_id,
                   'email' => $email
               ])
           ) {
               throw new RuntimeException(
                   'No se han podido comprobar las invitaciones.'
               );
           }

           if ($stmt->fetch(PDO::FETCH_ASSOC) !== false) {
               throw new LogicException(
                   'Ya existe una invitación pendiente para este correo.'
               );
           }

           $token = bin2hex(random_bytes(32));

           $invitation = new SpaceInvitation();
           $invitation->id_space = $space_id;
           $invitation->email = $email;
           $invitation->token_hash = hash('sha256', $token);
           $invitation->invited_by = $user_id;
           $invitation->expires_at = date(
               'Y-m-d H:i:s',
               time() + (7 * 24 * 60 * 60)
           );
           $invitation->accepted_at = null;
           $invitation->revoked_at = null;

           if (
               !$invitation->save() ||
               $invitation->id === null
           ) {
               throw new RuntimeException(
                   'No se ha podido crear la invitación.'
               );
           }

           if (!$db->commit()) {
               throw new RuntimeException(
                   'No se ha podido confirmar la invitación.'
               );
           }

           return [
               'invitation' => $invitation,
               'token' => $token
           ];
       }
       catch (Throwable $e) {
           if ($db->inTransaction()) {
               $db->rollBack();
           }

           throw $e;
       }
   }

    /**
     * Acepta una invitación y añade al destinatario como miembro.
     *
     * @param string $token Token recibido por el invitado.
     * @param int $user_id Identificador autenticado.
     * @return Space Espacio al que se ha incorporado.
     */
    public function accept(string $token, int $user_id): Space {
        if (
            $user_id <= 0 ||
            preg_match('/^[a-f0-9]{64}$/D', $token) !== 1
        ) {
            throw new InvalidArgumentException(
                'Los datos de la invitación no son válidos.'
            );
        }

        $db = ODB::getInstance();

        if (!$db->beginTransaction()) {
            throw new RuntimeException(
                'No se ha podido iniciar la transacción.'
            );
        }

        try {
            $token_hash = hash('sha256', $token);

            // Bloqueamos la invitación para impedir dobles aceptaciones.
            $stmt = $db->prepare(
                'SELECT id FROM `space_invitation`
                 WHERE token_hash = :token_hash
                 FOR UPDATE'
            );

            if (
                $stmt === false ||
                !$stmt->execute(['token_hash' => $token_hash])
            ) {
                throw new RuntimeException(
                    'No se ha podido consultar la invitación.'
                );
            }

            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!is_array($row)) {
                throw new LogicException(
                    'La invitación no está disponible.'
                );
            }

            $invitation = SpaceInvitation::findOne([
                'id' => (int) $row['id']
            ]);

            if (
                $invitation === null ||
                $invitation->accepted_at !== null ||
                $invitation->revoked_at !== null ||
                $invitation->expires_at === null ||
                strtotime($invitation->expires_at) <= time()
            ) {
                throw new LogicException(
                    'La invitación no está disponible.'
                );
            }

            $user = User::findOne(['id' => $user_id]);

            if (
                $user === null ||
                $user->email === null ||
                mb_strtolower($user->email) !== $invitation->email
            ) {
                throw new DomainException(
                    'La invitación pertenece a otro usuario.'
                );
            }

            $space = Space::findOne([
                'id' => $invitation->id_space
            ]);

            if ($space === null || $space->type !== 'shared') {
                throw new LogicException(
                    'El espacio de la invitación no está disponible.'
                );
            }

            // Coordinamos la aceptación con las bajas y
            // con la creación de nuevas invitaciones.
            $stmt = $db->prepare(
                'SELECT id
                 FROM `space`
                 WHERE id = :id
                 FOR UPDATE'
            );
            $stmt->execute(['id' => $space->id]);

            if ($stmt->fetch(PDO::FETCH_ASSOC) === false) {
                throw new LogicException(
                    'El espacio ya no está disponible.'
                );
            }

            $stmt = $db->prepare(
                'SELECT id, left_at
                 FROM `space_member`
                 WHERE id_space = :space_id
                   AND id_user = :user_id
                 FOR UPDATE'
            );
            $stmt->execute([
                'space_id' => $space->id,
                'user_id' => $user_id
            ]);

            $membership = $stmt->fetch(PDO::FETCH_ASSOC);

            if (is_array($membership)) {
                if ($membership['left_at'] === null) {
                    throw new LogicException(
                        'Ya perteneces a este espacio.'
                    );
                }

                // Reactivamos la membresía anterior.
                $stmt = $db->prepare(
                    'UPDATE `space_member`
                     SET left_at = NULL, joined_at = NOW()
                     WHERE id = :id'
                );
                $stmt->execute([
                    'id' => (int) $membership['id']
                ]);

                if ($stmt->rowCount() !== 1) {
                    throw new RuntimeException(
                        'No se ha podido reactivar la membresía.'
                    );
                }
            }
            else {
                // Primera incorporación al espacio.
                $member = new SpaceMember();
                $member->id_space = $space->id;
                $member->id_user = $user_id;
                $member->left_at = null;

                if (!$member->save()) {
                    throw new RuntimeException(
                        'No se ha podido añadir al usuario al espacio.'
                    );
                }
            }

            $invitation->accepted_at = date('Y-m-d H:i:s');

            if (!$invitation->save()) {
                throw new RuntimeException(
                    'No se ha podido completar la invitación.'
                );
            }

            if (!$db->commit()) {
                throw new RuntimeException(
                    'No se ha podido confirmar la aceptación.'
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

    /**
     * Comprueba que el usuario es propietario de un espacio compartido.
     *
     * @param int $space_id Identificador del espacio.
     * @param int $user_id Usuario autenticado.
     * @return Space Espacio autorizado.
     */
    private function requireSharedSpaceOwner(
        int $space_id,
        int $user_id
    ): Space {
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
                'No tienes permiso para administrar estas invitaciones.'
            );
        }

        return $space;
    }

    /**
     * Devuelve las invitaciones de un espacio compartido.
     *
     * @param int $space_id Identificador del espacio.
     * @param int $user_id Propietario autenticado.
     * @return list<SpaceInvitation> Invitaciones del espacio.
     */
    public function getBySpace(int $space_id, int $user_id): array {
        $this->requireSharedSpaceOwner($space_id, $user_id);

        return SpaceInvitation::where(
            ['id_space' => $space_id],
            ['order_by' => 'created_at#DESC']
        );
    }

    /**
     * Revoca una invitación pendiente.
     *
     * @param int $invitation_id Identificador de la invitación.
     * @param int $user_id Propietario autenticado.
     * @return SpaceInvitation Invitación revocada.
     */
    public function revoke(int $invitation_id, int $user_id): SpaceInvitation {
        if ($invitation_id <= 0 || $user_id <= 0) {
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
            $stmt = $db->prepare(
                'SELECT id, id_space, accepted_at, revoked_at, expires_at
                 FROM `space_invitation`
                 WHERE id = :id
                 FOR UPDATE'
            );

            if (
                $stmt === false ||
                !$stmt->execute(['id' => $invitation_id])
            ) {
                throw new RuntimeException(
                    'No se ha podido consultar la invitación.'
                );
            }

            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!is_array($row)) {
                throw new DomainException(
                    'No tienes permiso para administrar esta invitación.'
                );
            }

            $this->requireSharedSpaceOwner(
                (int) $row['id_space'],
                $user_id
            );

            if (
                $row['accepted_at'] !== null ||
                $row['revoked_at'] !== null ||
                strtotime($row['expires_at']) <= time()
            ) {
                throw new LogicException(
                    'La invitación ya no está pendiente.'
                );
            }

            $invitation = SpaceInvitation::findOne([
                'id' => $invitation_id
            ]);

            if ($invitation === null) {
                throw new LogicException(
                    'La invitación no está disponible.'
                );
            }

            $invitation->revoked_at = date('Y-m-d H:i:s');

            if (!$invitation->save()) {
                throw new RuntimeException(
                    'No se ha podido revocar la invitación.'
                );
            }

            if (!$db->commit()) {
                throw new RuntimeException(
                    'No se ha podido confirmar la revocación.'
                );
            }

            return $invitation;
        }
        catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            throw $e;
        }
    }
}

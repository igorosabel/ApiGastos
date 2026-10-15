<?php declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Service\Space;

use Osumi\OsumiFramework\Core\OService;
use Osumi\OsumiFramework\ORM\ODB;
use PDO;
use RuntimeException;
use Throwable;

class SpaceService extends OService {
    private readonly PDO $db;

    /**
     * Inicializa el servicio con una conexión de base de datos.
     *
     * @param PDO|null $db Conexión opcional para las pruebas.
     */
    public function __construct(?PDO $db = null) {
        $this->db = $db ?? ODB::getInstance();
    }

    /**
     * Obtiene los espacios de los que es miembro el usuario.
     *
     * @param int $user_id Identificador del usuario autenticado.
     * @return list<array{id: int, name: string, type: string, owner_id: int, is_owner: bool}>
     */
    public function getUserSpaces(int $user_id): array {
        if ($user_id <= 0) {
            throw new \InvalidArgumentException(
                'El identificador del usuario no es válido.'
            );
        }

        $stmt = $this->db->prepare(
            'SELECT s.id, s.name, s.type, s.owner_id
             FROM `space` s
             INNER JOIN `space_member` sm
                 ON sm.id_space = s.id
             WHERE sm.id_user = :user_id
             ORDER BY s.type ASC, s.name ASC, s.id ASC'
        );

        if ($stmt === false || !$stmt->execute(['user_id' => $user_id])) {
            throw new RuntimeException(
                'No se han podido consultar los espacios.'
            );
        }

        $spaces = [];

        while (($row = $stmt->fetch(PDO::FETCH_ASSOC)) !== false) {
            $owner_id = (int) $row['owner_id'];

            $spaces[] = [
                'id' => (int) $row['id'],
                'name' => $row['name'],
                'type' => $row['type'],
                'owner_id' => $owner_id,
                'is_owner' => $owner_id === $user_id
            ];
        }

        return $spaces;
    }

    /**
     * Crea un espacio compartido y añade a su propietario como miembro.
     *
     * @param int $user_id Identificador del propietario.
     * @param mixed $name Nombre propuesto.
     * @return array{id: int, name: string, type: string, owner_id: int, is_owner: bool}
     */
    public function createSharedSpace(int $user_id, mixed $name): array {
        if ($user_id <= 0) {
            throw new \InvalidArgumentException(
                'El identificador del usuario no es válido.'
            );
        }

        if (!is_string($name)) {
            throw new \InvalidArgumentException(
                'El nombre del espacio no es válido.'
            );
        }

        $name = trim($name);

        if ($name === '' || mb_strlen($name) > 100) {
            throw new \InvalidArgumentException(
                'El nombre del espacio debe tener entre 1 y 100 caracteres.'
            );
        }

        if (!$this->db->beginTransaction()) {
            throw new RuntimeException(
                'No se ha podido iniciar la transacción.'
            );
        }

        try {
            $stmt = $this->db->prepare(
                'INSERT INTO `space`
                    (`name`, `type`, `owner_id`, `personal_owner_id`)
                 VALUES (:name, :type, :owner_id, NULL)'
            );

            if (
                $stmt === false ||
                !$stmt->execute([
                    'name' => $name,
                    'type' => 'shared',
                    'owner_id' => $user_id
                ])
            ) {
                throw new RuntimeException(
                    'No se ha podido crear el espacio.'
                );
            }

            $space_id = (int) $this->db->lastInsertId();

            if ($space_id <= 0) {
                throw new RuntimeException(
                    'No se ha obtenido el identificador del espacio.'
                );
            }

            $stmt = $this->db->prepare(
                'INSERT INTO `space_member` (`id_space`, `id_user`)
                 VALUES (:space_id, :user_id)'
            );

            if (
                $stmt === false ||
                !$stmt->execute([
                    'space_id' => $space_id,
                    'user_id' => $user_id
                ])
            ) {
                throw new RuntimeException(
                    'No se ha podido añadir el propietario al espacio.'
                );
            }

            if (!$this->db->commit()) {
                throw new RuntimeException(
                    'No se ha podido confirmar la creación del espacio.'
                );
            }

            return [
                'id' => $space_id,
                'name' => $name,
                'type' => 'shared',
                'owner_id' => $user_id,
                'is_owner' => true
            ];
        }
        catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $e;
        }
    }
}

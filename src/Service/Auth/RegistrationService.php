<?php declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Service\Auth;

use Osumi\OsumiFramework\Core\OService;
use Osumi\OsumiFramework\ORM\ODB;
use PDO;
use PDOException;
use RuntimeException;
use Throwable;

final class RegistrationService extends OService {
    private readonly PDO $db;

    /**
     * Crea el servicio utilizando la conexión proporcionada o la de OFW.
     *
     * @param PDO|null $db Conexión opcional para pruebas.
     */
    public function __construct(?PDO $db = null) {
        $this->db = $db ?? ODB::getInstance();
    }

    /**
     * Registra un usuario y crea su espacio personal con una membresía.
     *
     * @return array{user_id: int, space_id: int}
     */
    public function register(
        mixed $name,
        mixed $email,
        mixed $password
    ): array {
        $data = RegistrationValidator::validate(
            $name,
            $email,
            $password
        );

        $hash = password_hash(
            $data['password'],
            PASSWORD_DEFAULT
        );

        if (!is_string($hash)) {
            throw new RuntimeException(
                'No se ha podido proteger la contraseña.'
            );
        }

        if (!$this->db->beginTransaction()) {
            throw new RuntimeException(
                'No se ha podido iniciar la transacción.'
            );
        }

        try {
            $stmt = $this->db->prepare(
                'INSERT INTO `user` (`name`, `email`, `password_hash`)
                 VALUES (:name, :email, :password_hash)'
            );
            $this->execute($stmt, [
                'name' => $data['name'],
                'email' => $data['email'],
                'password_hash' => $hash
            ]);

            $user_id = (int) $this->db->lastInsertId();

            if ($user_id <= 0) {
                throw new RuntimeException(
                    'No se ha obtenido el identificador del usuario.'
                );
            }

            $stmt = $this->db->prepare(
                'INSERT INTO `space`
                    (`name`, `type`, `owner_id`, `personal_owner_id`)
                 VALUES (:name, :type, :owner_id, :personal_owner_id)'
            );
            $this->execute($stmt, [
                'name' => 'Personal',
                'type' => 'personal',
                'owner_id' => $user_id,
                'personal_owner_id' => $user_id
            ]);

            $space_id = (int) $this->db->lastInsertId();

            if ($space_id <= 0) {
                throw new RuntimeException(
                    'No se ha obtenido el identificador del espacio.'
                );
            }

            $stmt = $this->db->prepare(
                'INSERT INTO `space_member` (`id_space`, `id_user`)
                 VALUES (:id_space, :id_user)'
            );
            $this->execute($stmt, [
                'id_space' => $space_id,
                'id_user' => $user_id
            ]);

            if (!$this->db->commit()) {
                throw new RuntimeException(
                    'No se ha podido confirmar el registro.'
                );
            }

            return [
                'user_id' => $user_id,
                'space_id' => $space_id
            ];
        }
        catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            if (
                $e instanceof PDOException &&
                ($e->errorInfo[1] ?? null) === 1062 &&
                str_contains(
                    (string) ($e->errorInfo[2] ?? ''),
                    'uq_user_email'
                )
            ) {
                throw new EmailAlreadyRegisteredException(
                    'Ya existe una cuenta con ese correo electrónico.',
                    0,
                    $e
                );
            }

            throw $e;
        }
    }

    /**
     * Ejecuta una sentencia preparada y comprueba su resultado.
     *
     * @param \PDOStatement|false $stmt Sentencia preparada.
     * @param array<string, mixed> $params Parámetros SQL.
     */
    private function execute(
        \PDOStatement|false $stmt,
        array $params
    ): void {
        if ($stmt === false || !$stmt->execute($params)) {
            throw new RuntimeException(
                'No se ha podido completar la operación de registro.'
            );
        }
    }
}

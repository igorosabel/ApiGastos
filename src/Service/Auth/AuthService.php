<?php declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Service\Auth;

use Osumi\OsumiFramework\Core\OService;
use Osumi\OsumiFramework\ORM\ODB;
use Osumi\OsumiFramework\Plugins\OToken;
use PDO;
use RuntimeException;

class AuthService extends OService {
    private readonly PDO $db;
    private readonly ?string $jwt_secret;

    /**
     * Inicializa las dependencias del servicio de autenticación.
     *
     * @param PDO|null $db Conexión opcional para pruebas.
     * @param string|null $jwt_secret Clave opcional para pruebas.
     */
    public function __construct(
        ?PDO $db = null,
        ?string $jwt_secret = null
    ) {
        $this->db = $db ?? ODB::getInstance();
        $this->jwt_secret = $jwt_secret;
    }

    /**
     * Comprueba las credenciales y emite un JWT de 24 horas.
     *
     * @param string $email Correo electrónico.
     * @param string $password Contraseña original.
     * @return array{token: string, expires_at: int, user_id: int, name: string, email: string}|null
     */
    public function login(string $email, string $password): ?array {
        $email = mb_strtolower(trim($email));

        $stmt = $this->db->prepare(
            'SELECT id, name, email, password_hash
             FROM `user`
             WHERE email = :email
             LIMIT 1'
        );

        if ($stmt === false || !$stmt->execute(['email' => $email])) {
            throw new RuntimeException(
                'No se ha podido consultar el usuario.'
            );
        }

        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (
            !is_array($user) ||
            !password_verify($password, $user['password_hash'])
        ) {
            return null;
        }

        $secret = $this->jwt_secret
            ?? $this->getConfig()->getExtra('jwt_secret');

        if (!is_string($secret) || strlen($secret) < 32) {
            throw new RuntimeException(
                'La clave de autenticación no está configurada correctamente.'
            );
        }

        $expires_at = time() + 86400;
        $token = new OToken($secret);
        $token->addParam('id', (int) $user['id']);
        $token->setEXP($expires_at);

        return [
            'token' => $token->getToken(),
            'expires_at' => $expires_at,
            'user_id' => (int) $user['id'],
            'name' => $user['name'],
            'email' => $user['email']
        ];
    }
}

<?php declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Middleware;

use Osumi\OsumiFramework\App\Service\Auth\AuthService;
use Osumi\OsumiFramework\Core\OMiddleware;

class LoginMiddleware {
    /**
     * Valida el token Bearer y publica el usuario autenticado.
     *
     * @param string $phase Fase del middleware.
     * @param array<string, mixed> $data Datos de la petición.
     * @return array<string, mixed> Resultado del middleware.
     */
    public static function handle(string $phase, array $data): array {
        if ($phase !== OMiddleware::PHASE_BEFORE) {
            return [];
        }

        $headers = $data['headers'] ?? [];
        $authorization = null;

        if (is_array($headers)) {
            foreach ($headers as $name => $value) {
                if (is_string($name) &&
                    strcasecmp($name, 'Authorization') === 0) {
                    $authorization = $value;
                    break;
                }
            }
        }

        if (
            !is_string($authorization) ||
            preg_match('/^Bearer ([^\s]+)$/iD', $authorization, $matches) !== 1
        ) {
            return self::unauthorized();
        }

        /** @var AuthService $service */
        $service = inject(AuthService::class);
        $user = $service->authenticateToken($matches[1]);

        if ($user === null) {
            return self::unauthorized();
        }

        return [
            'context' => [
                'id' => $user['id'],
                'name' => $user['name'],
                'email' => $user['email']
            ]
        ];
    }

    /**
     * Devuelve el resultado HTTP para autenticación rechazada.
     *
     * @return array<string, mixed> Respuesta del middleware.
     */
    private static function unauthorized(): array {
        return [
            'stop' => true,
            'status_code' => 401,
            'message' => 'No autorizado.'
        ];
    }
}

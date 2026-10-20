<?php declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Service\Email;

use InvalidArgumentException;
use Osumi\OsumiFramework\Core\OService;

class InvitationEmailContentService extends OService {
    /**
     * Prepara el asunto y HTML de una invitación.
     *
     * @param string $frontend_url URL base pública del frontend.
     * @param string $space_name Nombre del espacio.
     * @param string $token Token original de la invitación.
     * @param string $expires_at Fecha de caducidad.
     * @return array{subject: string, html: string}
     */
    public function build(
        string $frontend_url,
        string $space_name,
        string $token,
        string $expires_at
    ): array {
        $frontend_url = rtrim(trim($frontend_url), '/');
        $space_name = trim($space_name);
        $expires_at = trim($expires_at);

        if (
            filter_var($frontend_url, FILTER_VALIDATE_URL) === false ||
            parse_url($frontend_url, PHP_URL_SCHEME) !== 'https' ||
            parse_url($frontend_url, PHP_URL_HOST) === null ||
            parse_url($frontend_url, PHP_URL_USER) !== null ||
            parse_url($frontend_url, PHP_URL_PASS) !== null ||
            parse_url($frontend_url, PHP_URL_QUERY) !== null ||
            parse_url($frontend_url, PHP_URL_FRAGMENT) !== null ||
            preg_match('/^[a-f0-9]{64}$/D', $token) !== 1 ||
            $space_name === '' ||
            $expires_at === '' ||
            strpbrk($space_name . $expires_at, "\r\n") !== false
        ) {
            throw new InvalidArgumentException(
                'Los datos del correo de invitación no son válidos.'
            );
        }

        $url = $frontend_url . '/invitacion#token=' . $token;

        $safe_url = htmlspecialchars(
            $url,
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        );
        $safe_name = htmlspecialchars(
            $space_name,
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        );
        $safe_expiration = htmlspecialchars(
            $expires_at,
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        );

        $subject = "Gastos: invitación a «{$space_name}»";

        $html = <<<HTML
<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Invitación a Gastos</title>
</head>
<body style="margin:0;padding:24px;font-family:Arial,sans-serif;color:#222222;">
    <div style="max-width:560px;margin:0 auto;">
        <h1 style="font-size:24px;">Gastos</h1>

        <h2 style="font-size:20px;">
            Te han invitado a {$safe_name}
        </h2>

        <p>
            Has recibido una invitación para participar
            en el espacio compartido
            <strong>{$safe_name}</strong> de Gastos.
        </p>

        <p>
            Para incorporarte, pulsa el siguiente enlace:
        </p>

        <p style="margin:32px 0;">
            <a href="{$safe_url}"
               style="background:#2563eb;color:#ffffff;padding:14px 24px;text-decoration:none;border-radius:6px;display:inline-block;">
                Aceptar invitación
            </a>
        </p>

        <p>
            Si todavía no tienes cuenta en Gastos,
            podrás registrarte antes de aceptar.
        </p>

        <p>
            Esta invitación caduca el {$safe_expiration}.
        </p>

        <p style="font-size:13px;color:#666666;">
            Si el botón no funciona, copia este enlace:
        </p>

        <p style="font-size:13px;overflow-wrap:anywhere;">
            <a href="{$safe_url}">{$safe_url}</a>
        </p>

        <hr style="border:0;border-top:1px solid #dddddd;margin:32px 0;">

        <p style="font-size:12px;color:#666666;">
            Si no esperabas esta invitación,
            puedes ignorar el mensaje.
        </p>
    </div>
</body>
</html>
HTML;

        return [
            'subject' => $subject,
            'html' => $html
        ];
    }
}

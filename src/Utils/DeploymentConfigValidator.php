<?php declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Utils;

use Osumi\OsumiFramework\Core\OConfig;

class DeploymentConfigValidator {
    /**
     * Revisa la configuración efectiva de OFW para
     * un despliegue de producción.
     *
     * Nunca incluye valores privados en los errores.
     *
     * @param OConfig $config Configuración efectiva.
     * @return list<string> Problemas encontrados.
     */
    public function validate(OConfig $config): array {
        $errors = [];

        foreach (['host', 'user', 'pass', 'name'] as $field) {
            $value = $config->getDB($field);

            if (trim($value) === '' || $value === $field) {
                $errors[] =
                    "db.{$field}: configura un valor real.";
            }
        }

        $url = $config->getUrl('base');

        if (
            filter_var($url, FILTER_VALIDATE_URL) === false ||
            parse_url($url, PHP_URL_SCHEME) !== 'https' ||
            parse_url($url, PHP_URL_USER) !== null ||
            parse_url($url, PHP_URL_PASS) !== null
        ) {
            $errors[] =
                'base_url: debe ser una URL HTTPS válida, sin credenciales.';
        }

        $secret = $config->getExtra('jwt_secret');

        if (
            !is_string($secret) ||
            strlen($secret) < 32 ||
            trim($secret) === ''
        ) {
            $errors[] =
                'extra.jwt_secret: configura un secreto de al menos 32 caracteres.';
        }

        if ($config->getLog('level') !== 'ERROR') {
            $errors[] =
                'log_level: utiliza ERROR en producción.';
        }

        // URL pública donde se reciben las invitaciones.
        $frontend_url = $config->getExtra('frontend_url');

        if (
            !is_string($frontend_url) ||
            filter_var($frontend_url, FILTER_VALIDATE_URL) === false ||
            parse_url($frontend_url, PHP_URL_SCHEME) !== 'https' ||
            parse_url($frontend_url, PHP_URL_HOST) === null ||
            parse_url($frontend_url, PHP_URL_USER) !== null ||
            parse_url($frontend_url, PHP_URL_PASS) !== null ||
            parse_url($frontend_url, PHP_URL_QUERY) !== null ||
            parse_url($frontend_url, PHP_URL_FRAGMENT) !== null ||
            str_contains($frontend_url, 'example.com')
        ) {
            $errors[] =
                'extra.frontend_url: configura la URL HTTPS real del frontend.';
        }

        // Remitente visible de los mensajes.
        $from = $config->getMailingFrom();

        if (
            filter_var($from, FILTER_VALIDATE_EMAIL) === false ||
            str_ends_with($from, '@example.com')
        ) {
            $errors[] =
                'mailing_from: configura una dirección remitente real.';
        }

        // Parámetros SMTP que utiliza OEmailSMTP.
        $smtp = $config->getPluginConfig('email_smtp');

        if (!is_array($smtp)) {
            $errors[] =
                'plugins.email_smtp: falta la configuración SMTP.';
        }
        else {
            foreach (['host', 'user', 'pass'] as $field) {
                $value = $smtp[$field] ?? null;

                if (
                    !is_string($value) ||
                    trim($value) === '' ||
                    in_array(
                        $value,
                        [
                            'smtp.example.com',
                            'user@email.com',
                            'smtp-password',
                            'password'
                        ],
                        true
                    )
                ) {
                    $errors[] =
                        "plugins.email_smtp.{$field}: configura un valor real.";
                }
            }

            $port = $smtp['port'] ?? null;

            if (
                !is_int($port) ||
                $port <= 0 ||
                $port > 65535
            ) {
                $errors[] =
                    'plugins.email_smtp.port: configura un puerto válido.';
            }

            if (
                !in_array(
                    $smtp['secure'] ?? null,
                    ['tls', 'ssl'],
                    true
                )
            ) {
                $errors[] =
                    'plugins.email_smtp.secure: utiliza tls o ssl.';
            }
        }

        return $errors;
    }
}

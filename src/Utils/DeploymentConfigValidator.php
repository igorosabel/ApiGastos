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

        return $errors;
    }
}

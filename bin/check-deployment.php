<?php declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

use Osumi\OsumiFramework\App\Utils\DeploymentConfigValidator;
use Osumi\OsumiFramework\Core\OConfig;

$base_dir = dirname(__DIR__) . DIRECTORY_SEPARATOR;
$config_path = $base_dir . 'src/Config/Config.json';

if (!is_file($config_path)) {
    fwrite(
        STDERR,
        "ERROR: falta src/Config/Config.json.\n"
    );
    exit(1);
}

try {
    // OFW carga también la configuración específica
    // del entorno si está definida y existe.
    $config = new OConfig($base_dir);

    $errors = (new DeploymentConfigValidator())
        ->validate($config);
}
catch (Throwable) {
    // No mostrar el contenido de la excepción:
    // podría incluir información del entorno.
    fwrite(
        STDERR,
        "ERROR: no se ha podido analizar la configuración de OFW.\n"
    );
    exit(1);
}

if ($errors !== []) {
    fwrite(
        STDERR,
        "La configuración no está preparada para producción:\n"
    );

    foreach ($errors as $error) {
        fwrite(STDERR, "- {$error}\n");
    }

    exit(1);
}

fwrite(
    STDOUT,
    "Configuración básica de despliegue validada.\n"
);

exit(0);

<?php declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Osumi\OsumiFramework\Core\OCore;

$core = new OCore();

// Registrar el manejador antes de inicializar OFW.
// Así también protege frente a errores durante load().
set_exception_handler([$core, 'errorHandler']);

$core->load();
$core->run();

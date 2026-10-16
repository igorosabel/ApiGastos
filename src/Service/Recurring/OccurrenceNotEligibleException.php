<?php declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Service\Recurring;

use InvalidArgumentException;

/**
 * Indica que una mensualidad nueva no puede generarse
 * por el estado o las fechas actuales de su plantilla.
 *
 * La generación individual conserva el contrato de
 * InvalidArgumentException (HTTP 400).
 */
class OccurrenceNotEligibleException extends InvalidArgumentException {
}

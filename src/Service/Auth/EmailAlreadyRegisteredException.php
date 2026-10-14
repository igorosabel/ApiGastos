<?php declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Service\Auth;

use DomainException;

/**
 * Indica que ya existe una cuenta con el correo solicitado.
 */
final class EmailAlreadyRegisteredException extends DomainException {
}

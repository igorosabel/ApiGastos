<?php declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Service\Auth;

use InvalidArgumentException;

final class RegistrationValidator {
    /**
     * @return array{name: string, email: string, password: string}
     */
    public static function validate(
        mixed $name,
        mixed $email,
        mixed $password
    ): array {
        if (!is_string($name)) {
            throw new InvalidArgumentException('Nombre no válido.');
        }

        $name = trim($name);

        if ($name === '' || mb_strlen($name) > 100) {
            throw new InvalidArgumentException(
                'El nombre debe tener entre 1 y 100 caracteres.'
            );
        }

        if (!is_string($email)) {
            throw new InvalidArgumentException('Email no válido.');
        }

        $email = mb_strtolower(trim($email));

        if (
            strlen($email) > 254 ||
            filter_var($email, FILTER_VALIDATE_EMAIL) === false
        ) {
            throw new InvalidArgumentException(
                'El email no tiene un formato válido.'
            );
        }

        if (
            !is_string($password) ||
            strlen($password) < 12 ||
            strlen($password) > 1024
        ) {
            throw new InvalidArgumentException(
                'La contraseña debe tener entre 12 y 1024 bytes.'
            );
        }

        return [
            'name' => $name,
            'email' => $email,
            'password' => $password
        ];
    }
}

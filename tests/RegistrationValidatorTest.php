<?php declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Osumi\OsumiFramework\App\Service\Auth\RegistrationValidator;

function check(bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function rejects(
    mixed $name,
    mixed $email,
    mixed $password
): void {
    try {
        RegistrationValidator::validate($name, $email, $password);
    }
    catch (InvalidArgumentException) {
        return;
    }

    throw new RuntimeException(
        'Se han aceptado datos inválidos.'
    );
}

$result = RegistrationValidator::validate(
    '  Iñigo  ',
    '  TEST@EXAMPLE.COM  ',
    'una contraseña larga'
);

check($result['name'] === 'Iñigo', 'Nombre');
check($result['email'] === 'test@example.com', 'Email');
check(
    $result['password'] === 'una contraseña larga',
    'Contraseña'
);

rejects('', 'test@example.com', '123456789012');
rejects(str_repeat('a', 101), 'test@example.com', '123456789012');
rejects('Prueba', 'email-incorrecto', '123456789012');
rejects('Prueba', 'test@example.com', '123');
rejects('Prueba', 'test@example.com', ['no-valida']);

echo "OK: todas las pruebas de validación han pasado.\n";

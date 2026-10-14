<?php declare(strict_types=1);

use Osumi\OsumiFramework\App\Service\Auth\RegistrationValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RegistrationValidatorTest extends TestCase {
    /**
     * Comprueba la normalización de los datos válidos.
     */
    public function testValidRegistration(): void {
        $result = RegistrationValidator::validate(
            '  Iñigo  ',
            '  TEST@EXAMPLE.COM  ',
            'una contraseña larga'
        );

        $this->assertSame('Iñigo', $result['name']);
        $this->assertSame('test@example.com', $result['email']);
        $this->assertSame(
            'una contraseña larga',
            $result['password']
        );
    }

    /**
     * Comprueba el rechazo de entradas inválidas.
     */
    #[DataProvider('invalidRegistrations')]
    public function testInvalidRegistration(
        mixed $name,
        mixed $email,
        mixed $password
    ): void {
        $this->expectException(InvalidArgumentException::class);

        RegistrationValidator::validate(
            $name,
            $email,
            $password
        );
    }

    /**
     * Proporciona casos inválidos para el registro.
     *
     * @return iterable<string, array{mixed, mixed, mixed}>
     */
    public static function invalidRegistrations(): iterable {
        yield 'nombre vacío' => [
            '',
            'test@example.com',
            '123456789012'
        ];

        yield 'nombre demasiado largo' => [
            str_repeat('a', 101),
            'test@example.com',
            '123456789012'
        ];

        yield 'email incorrecto' => [
            'Prueba',
            'email-incorrecto',
            '123456789012'
        ];

        yield 'contraseña corta' => [
            'Prueba',
            'test@example.com',
            '123'
        ];

        yield 'contraseña no válida' => [
            'Prueba',
            'test@example.com',
            ['no-valida']
        ];
    }
}

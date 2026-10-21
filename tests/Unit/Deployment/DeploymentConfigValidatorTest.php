<?php declare(strict_types=1);

use Osumi\OsumiFramework\App\Utils\DeploymentConfigValidator;
use Osumi\OsumiFramework\Core\OConfig;
use PHPUnit\Framework\TestCase;

final class DeploymentConfigValidatorTest extends TestCase {
    /**
     * Una configuración válida no genera errores.
     */
    public function testValidConfiguration(): void {
        $errors = (new DeploymentConfigValidator())
            ->validate($this->makeConfig());

        $this->assertSame([], $errors);
    }

    /**
     * Detecta los valores de ejemplo y la falta
     * de configuración segura para producción.
     */
    public function testRejectsExampleConfiguration(): void {
        $config = $this->makeConfig(
            host: 'host',
            user: 'user',
            pass: 'pass',
            name: 'name',
            url: 'http://api.example.test/',
            secret: 'value',
            log_level: 'DEBUG'
        );

        $errors = (new DeploymentConfigValidator())
            ->validate($config);

        $this->assertCount(7, $errors);

        foreach ([
            'db.host',
            'db.user',
            'db.pass',
            'db.name',
            'base_url',
            'extra.jwt_secret',
            'log_level'
        ] as $field) {
            $this->assertStringContainsString(
                $field,
                implode("\n", $errors)
            );
        }
    }

    /**
     * Los mensajes nunca incluyen contraseñas
     * o secretos de configuración.
     */
    public function testErrorsDoNotExposeSecrets(): void {
        $password = 'password-private-sentinel';
        $secret = 'jwt-private-sentinel';

        $config = $this->makeConfig(
            host: '',
            pass: $password,
            url: 'http://api.example.test/',
            secret: $secret
        );

        $errors = (new DeploymentConfigValidator())
            ->validate($config);

        $output = implode("\n", $errors);

        $this->assertNotSame([], $errors);
        $this->assertStringNotContainsString(
            $password,
            $output
        );
        $this->assertStringNotContainsString(
            $secret,
            $output
        );
    }

    /**
     * Una URL con credenciales incrustadas
     * no es válida para el despliegue.
     */
    public function testRejectsCredentialsInBaseUrl(): void {
        $config = $this->makeConfig(
            url: 'https://usuario:clave@api.example.test/'
        );

        $errors = (new DeploymentConfigValidator())
            ->validate($config);

        $this->assertCount(1, $errors);
        $this->assertStringContainsString(
            'base_url',
            $errors[0]
        );
    }

    /**
     * Detecta una configuración incompleta del correo.
     */
    public function testRejectsInvalidEmailConfiguration(): void {
        $config = $this->makeConfig(
            mailing_from: '',
            frontend_url: 'http://gastos.example.test',
            smtp: []
        );

        $errors = (new DeploymentConfigValidator())
            ->validate($config);

        $output = implode("\n", $errors);

        $this->assertStringContainsString(
            'mailing_from',
            $output
        );
        $this->assertStringContainsString(
            'extra.frontend_url',
            $output
        );
        $this->assertStringContainsString(
            'plugins.email_smtp.host',
            $output
        );
        $this->assertStringContainsString(
            'plugins.email_smtp.port',
            $output
        );
    }

    /**
     * Crea una configuración simulada sin leer
     * archivos privados ni conectar a MariaDB.
     */
    private function makeConfig(
        string $host = 'db.internal',
        string $user = 'gastos',
        string $pass = 'private-password',
        string $name = 'gastos',
        string $url = 'https://api.example.test/',
        string $secret = '0123456789abcdef0123456789abcdef',
        string $log_level = 'ERROR',
        string $mailing_from = 'avisos@example.test',
        string $frontend_url = 'https://gastos.example.test',
        ?array $smtp = null
    ): OConfig {
        $config = $this->createStub(OConfig::class);

        $db = compact('host', 'user', 'pass', 'name');

        $config->method('getDB')->willReturnCallback(
            static fn(string $key): string => $db[$key]
        );

        $config->method('getUrl')->willReturnCallback(
            static fn(string $key): string => $url
        );

        $config->method('getExtra')->willReturnCallback(
            static fn(string $key): ?string => match ($key) {
                'jwt_secret' => $secret,
                'frontend_url' => $frontend_url,
                default => null
            }
        );

        $config->method('getLog')->willReturnCallback(
            static fn(string $key): string => $log_level
        );

        $config->method('getMailingFrom')
            ->willReturn($mailing_from);

        $config->method('getPluginConfig')
            ->willReturn($smtp ?? [
                'host' => 'smtp.internal.test',
                'port' => 587,
                'secure' => 'tls',
                'user' => 'avisos@example.test',
                'pass' => 'private-smtp-password'
            ]);

        return $config;
    }
}

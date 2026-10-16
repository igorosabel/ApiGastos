<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class CorsConfigurationTest extends TestCase {
    /**
     * Comprueba que la configuración de ejemplo
     * declara explícitamente la política CORS
     * abierta acordada para la versión 1.0.
     */
    public function testCorsIsExplicitlyEnabled(): void {
        $path = dirname(__DIR__, 3) .
            '/src/Config/Config.json.example';

        $this->assertFileExists($path);

        $content = file_get_contents($path);
        $this->assertIsString($content);

        $config = json_decode(
            $content,
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        $this->assertIsArray($config);
        $this->assertArrayHasKey(
            'allow-cross-origin',
            $config
        );
        $this->assertSame(
            true,
            $config['allow-cross-origin']
        );
    }
}

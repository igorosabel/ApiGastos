<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class FrontControllerBootstrapTest extends TestCase {
    /**
     * Comprueba el orden de inicialización del
     * controlador frontal de la aplicación.
     *
     * El manejador debe registrarse antes de
     * ejecutar OCore::load().
     */
    public function testExceptionHandlerIsRegisteredBeforeLoadingFramework(): void {
        $path = dirname(__DIR__, 3) .
            '/public/index.php';

        $this->assertFileExists($path);

        $source = file_get_contents($path);

        $this->assertIsString($source);

        $autoload = $this->findOffset(
            $source,
            "require_once __DIR__ . '/../vendor/autoload.php';"
        );

        $construct = $this->findOffset(
            $source,
            '$core = new OCore();'
        );

        $handler = $this->findOffset(
            $source,
            "set_exception_handler([\$core, 'errorHandler']);"
        );

        $load = $this->findOffset(
            $source,
            '$core->load();'
        );

        $run = $this->findOffset(
            $source,
            '$core->run();'
        );

        // Primero cargar Composer.
        $this->assertLessThan($construct, $autoload);

        // Después construir el núcleo.
        $this->assertLessThan($handler, $construct);

        // Proteger la inicialización de OFW.
        $this->assertLessThan($load, $handler);

        // Ejecutar la aplicación solo tras cargar OFW.
        $this->assertLessThan($run, $load);
    }

    /**
     * Localiza una instrucción obligatoria
     * dentro del controlador frontal.
     *
     * @param string $source Código del archivo.
     * @param string $fragment Instrucción esperada.
     * @return int Posición encontrada.
     */
    private function findOffset(
        string $source,
        string $fragment
    ): int {
        $offset = strpos($source, $fragment);

        $this->assertNotFalse(
            $offset,
            "No se ha encontrado la instrucción: {$fragment}"
        );

        return $offset;
    }
}

<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ApacheRewriteTest extends TestCase {
    /**
     * Comprueba que la redirección de www
     * nunca degrada una petición a HTTP.
     */
    public function testWwwRedirectUsesHttps(): void {
        $rules = $this->loadRules();

        $this->assertStringContainsString(
            'RewriteCond %{HTTP_HOST} ^www\.(.*)$ [NC]',
            $rules
        );

        $this->assertStringContainsString(
            'RewriteRule ^(.*)$ https://%1/$1 [R=301,L]',
            $rules
        );

        $this->assertDoesNotMatchRegularExpression(
            '/^\s*RewriteRule\s+[^\r\n]*\s+http:\/\/\S+/mi',
            $rules
        );
    }

    /**
     * Comprueba que se conserva la cabecera
     * Authorization al procesar peticiones.
     */
    public function testAuthorizationHeaderIsForwarded(): void {
        $rules = $this->loadRules();

        $this->assertStringContainsString(
            'RewriteCond %{HTTP:Authorization} ^(.*)',
            $rules
        );

        $this->assertStringContainsString(
            'RewriteRule .* - [e=HTTP_AUTHORIZATION:%1]',
            $rules
        );
    }

    /**
     * Comprueba que las rutas dinámicas siguen
     * llegando al controlador frontal de OFW.
     */
    public function testFrontControllerRewriteIsPreserved(): void {
        $rules = $this->loadRules();

        $this->assertStringContainsString(
            'RewriteCond %{REQUEST_FILENAME} !-f',
            $rules
        );

        $this->assertStringContainsString(
            'RewriteRule ^(.*)$ index.php [QSA,L]',
            $rules
        );
    }

    /**
     * Comprueba que Apache no permite generar
     * listados automáticos de directorios.
     */
    public function testDirectoryIndexingIsDisabled(): void {
        $rules = $this->loadRules();

        $this->assertStringContainsString(
            'Options -Indexes +FollowSymLinks +ExecCGI',
            $rules
        );
    }

    /**
     * Comprueba que los archivos ocultos quedan
     * protegidos frente a peticiones HTTP.
     */
    public function testHiddenFilesAreDenied(): void {
        $rules = $this->loadRules();

        $start = strpos(
            $rules,
            '<FilesMatch "^\.">'
        );

        $this->assertNotFalse(
            $start,
            'Falta la regla de protección de archivos ocultos.'
        );

        $end = strpos(
            $rules,
            '</FilesMatch>',
            $start
        );

        $this->assertNotFalse(
            $end,
            'El bloque FilesMatch no está cerrado.'
        );

        $block = substr(
            $rules,
            $start,
            $end - $start
        );

        $this->assertStringContainsString(
            'Require all denied',
            $block
        );
    }

    /**
     * Recupera las reglas de Apache del proyecto.
     *
     * @return string Contenido del archivo .htaccess.
     */
    private function loadRules(): string {
        $path = dirname(__DIR__, 3) .
            '/public/.htaccess';

        $this->assertFileExists($path);

        $rules = file_get_contents($path);

        $this->assertIsString($rules);

        return $rules;
    }
}

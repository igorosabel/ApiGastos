<?php declare(strict_types=1);

use Osumi\OsumiFramework\App\Service\Email\InvitationEmailContentService;
use PHPUnit\Framework\TestCase;

final class InvitationEmailContentServiceTest extends TestCase {
    /**
     * Genera el asunto y un enlace de aceptación.
     */
    public function testBuildsInvitationEmail(): void {
        $service = new InvitationEmailContentService();
        $token = str_repeat('a', 64);

        $message = $service->build(
            'https://gastos.example.test/',
            'Familia',
            $token,
            '2026-10-27 12:00:00'
        );

        $this->assertSame(
            'Gastos: invitación a «Familia»',
            $message['subject']
        );

        $this->assertStringContainsString(
            'https://gastos.example.test/invitacion#token=' . $token,
            $message['html']
        );

        $this->assertStringContainsString(
            '2026-10-27 12:00:00',
            $message['html']
        );

        $this->assertStringContainsString(
            'Aceptar invitación',
            $message['html']
        );
    }

    /**
     * Escapa los caracteres especiales del nombre.
     */
    public function testEscapesSpaceName(): void {
        $service = new InvitationEmailContentService();

        $message = $service->build(
            'https://gastos.example.test',
            'Familia <script>alert("x")</script>',
            str_repeat('a', 64),
            '2026-10-27 12:00:00'
        );

        $this->assertStringContainsString(
            'Familia &lt;script&gt;',
            $message['html']
        );

        $this->assertStringNotContainsString(
            '<script>',
            $message['html']
        );
    }

    /**
     * Rechaza URLs que no utilizan HTTPS.
     */
    public function testRejectsInsecureFrontendUrl(): void {
        $service = new InvitationEmailContentService();

        $this->expectException(
            InvalidArgumentException::class
        );

        $service->build(
            'http://gastos.example.test',
            'Familia',
            str_repeat('a', 64),
            '2026-10-27 12:00:00'
        );
    }

    /**
     * No permite inyectar fragmentos en la URL base.
     */
    public function testRejectsFrontendUrlWithFragment(): void {
        $service = new InvitationEmailContentService();

        $this->expectException(
            InvalidArgumentException::class
        );

        $service->build(
            'https://gastos.example.test/#otra-ruta',
            'Familia',
            str_repeat('a', 64),
            '2026-10-27 12:00:00'
        );
    }

    /**
     * Rechaza tokens que no respetan el contrato.
     */
    public function testRejectsInvalidToken(): void {
        $service = new InvitationEmailContentService();

        $this->expectException(
            InvalidArgumentException::class
        );

        $service->build(
            'https://gastos.example.test',
            'Familia',
            'token-invalido',
            '2026-10-27 12:00:00'
        );
    }

    /**
     * Impide incluir saltos de línea en el asunto.
     */
    public function testRejectsHeaderInjection(): void {
        $service = new InvitationEmailContentService();

        $this->expectException(
            InvalidArgumentException::class
        );

        $service->build(
            'https://gastos.example.test',
            "Familia\r\nBcc: otro@example.com",
            str_repeat('a', 64),
            '2026-10-27 12:00:00'
        );
    }
}

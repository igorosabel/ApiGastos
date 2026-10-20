<?php declare(strict_types=1);

use Osumi\OsumiFramework\App\Service\Email\SmtpEmailService;
use Osumi\OsumiFramework\Plugins\OEmailSMTP;
use PHPUnit\Framework\TestCase;

final class SmtpEmailServiceTest extends TestCase {
    /**
     * Comprueba la configuración del correo
     * y el reconocimiento del envío correcto.
     */
    public function testSendsHtmlEmail(): void {
        $mailer = $this->createMock(OEmailSMTP::class);

        $mailer->expects($this->once())
            ->method('setFrom')
            ->with('avisos@example.com', 'Gastos');

        $mailer->expects($this->once())
            ->method('addRecipient')
            ->with('destino@example.com');

        $mailer->expects($this->once())
            ->method('setSubject')
            ->with('Invitación a Gastos');

        $mailer->expects($this->once())
            ->method('setMessage')
            ->with('<p>Hola</p>');

        $mailer->expects($this->once())
            ->method('send')
            ->willReturn([
                'status' => 'ok',
                'mens' => ''
            ]);

        $mailer->method('getResultOk')
            ->willReturn(['destino@example.com']);

        $service = $this->makeService($mailer);

        $this->assertTrue(
            $service->send(
                'avisos@example.com',
                'destino@example.com',
                'Invitación a Gastos',
                '<p>Hola</p>'
            )
        );
    }

    /**
     * Comprueba que un error SMTP se informa
     * como resultado negativo.
     */
    public function testReportsSmtpFailure(): void {
        $mailer = $this->createStub(OEmailSMTP::class);

        $mailer->method('send')
            ->willReturn([
                'status' => 'error',
                'mens' => 'Error SMTP'
            ]);

        $mailer->method('getResultOk')
            ->willReturn([]);

        $service = $this->makeService($mailer);

        $this->assertFalse(
            $service->send(
                'avisos@example.com',
                'destino@example.com',
                'Invitación a Gastos',
                '<p>Hola</p>'
            )
        );
    }

    /**
     * Un estado OK sin destinatario confirmado
     * no debe considerarse un envío correcto.
     */
    public function testRequiresConfirmedRecipient(): void {
        $mailer = $this->createStub(OEmailSMTP::class);

        $mailer->method('send')
            ->willReturn([
                'status' => 'ok',
                'mens' => ''
            ]);

        $mailer->method('getResultOk')
            ->willReturn([]);

        $service = $this->makeService($mailer);

        $this->assertFalse(
            $service->send(
                'avisos@example.com',
                'destino@example.com',
                'Invitación a Gastos',
                '<p>Hola</p>'
            )
        );
    }

    /**
     * Rechaza direcciones inválidas sin
     * intentar realizar un envío SMTP.
     */
    public function testRejectsInvalidRecipient(): void {
        $mailer = $this->createMock(OEmailSMTP::class);

        $mailer->expects($this->never())
            ->method('send');

        $service = $this->makeService($mailer);

        $this->expectException(
            InvalidArgumentException::class
        );

        $service->send(
            'avisos@example.com',
            'correo-invalido',
            'Invitación',
            '<p>Hola</p>'
        );
    }

    /**
     * Rechaza mensajes sin contenido.
     */
    public function testRejectsEmptyMessage(): void {
        $mailer = $this->createMock(OEmailSMTP::class);

        $mailer->expects($this->never())
            ->method('send');

        $service = $this->makeService($mailer);

        $this->expectException(
            InvalidArgumentException::class
        );

        $service->send(
            'avisos@example.com',
            'destino@example.com',
            'Invitación',
            '   '
        );
    }

    /**
     * Construye el servicio con un transporte simulado.
     *
     * @param OEmailSMTP $mailer Cliente simulado.
     *
     * @return SmtpEmailService Servicio bajo prueba.
     */
    private function makeService(
        OEmailSMTP $mailer
    ): SmtpEmailService {
        return new class($mailer) extends SmtpEmailService {
            /**
             * @param OEmailSMTP $mailer Cliente simulado.
             */
            public function __construct(
                private OEmailSMTP $mailer
            ) {}

            /**
             * Devuelve el transporte simulado.
             *
             * @return OEmailSMTP Cliente simulado.
             */
            protected function createMailer(): OEmailSMTP {
                return $this->mailer;
            }
        };
    }
}

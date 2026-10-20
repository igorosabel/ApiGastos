<?php declare(strict_types=1);

use Osumi\OsumiFramework\App\Service\Email\InvitationEmailContentService;
use Osumi\OsumiFramework\App\Service\Email\InvitationEmailDeliveryService;
use Osumi\OsumiFramework\App\Service\Email\SmtpEmailService;
use PHPUnit\Framework\TestCase;

final class InvitationEmailDeliveryServiceTest extends TestCase {
    /**
     * Comprueba el envío de una invitación válida.
     */
    public function testSendsInvitation(): void {
        $token = str_repeat('a', 64);
        $smtp = $this->createMock(SmtpEmailService::class);

        $smtp->expects($this->once())
            ->method('send')
            ->with(
                'avisos@example.com',
                'invitado@example.com',
                'Gastos: invitación a «Familia»',
                $this->callback(
                    static fn(string $html): bool =>
                        str_contains(
                            $html,
                            'https://gastos.example.test/invitacion#token=' . $token
                        ) &&
                        str_contains(
                            $html,
                            '2026-10-27 12:00:00'
                        )
                )
            )
            ->willReturn(true);

        $service = $this->makeService($smtp);

        $this->assertTrue(
            $service->send(
                'avisos@example.com',
                'https://gastos.example.test',
                'invitado@example.com',
                'Familia',
                $token,
                '2026-10-27 12:00:00'
            )
        );
    }

    /**
     * Propaga el resultado negativo del transporte.
     */
    public function testReportsDeliveryFailure(): void {
        $smtp = $this->createMock(SmtpEmailService::class);

        $smtp->expects($this->once())
            ->method('send')
            ->willReturn(false);

        $service = $this->makeService($smtp);

        $this->assertFalse(
            $service->send(
                'avisos@example.com',
                'https://gastos.example.test',
                'invitado@example.com',
                'Familia',
                str_repeat('a', 64),
                '2026-10-27 12:00:00'
            )
        );
    }

    /**
     * Un token inválido no provoca intentos SMTP.
     */
    public function testRejectsInvalidTokenBeforeSending(): void {
        $smtp = $this->createMock(SmtpEmailService::class);

        $smtp->expects($this->never())
            ->method('send');

        $service = $this->makeService($smtp);

        $this->expectException(
            InvalidArgumentException::class
        );

        $service->send(
            'avisos@example.com',
            'https://gastos.example.test',
            'invitado@example.com',
            'Familia',
            'token-invalido',
            '2026-10-27 12:00:00'
        );
    }

    /**
     * Una URL insegura no provoca intentos SMTP.
     */
    public function testRejectsInvalidUrlBeforeSending(): void {
        $smtp = $this->createMock(SmtpEmailService::class);

        $smtp->expects($this->never())
            ->method('send');

        $service = $this->makeService($smtp);

        $this->expectException(
            InvalidArgumentException::class
        );

        $service->send(
            'avisos@example.com',
            'http://gastos.example.test',
            'invitado@example.com',
            'Familia',
            str_repeat('a', 64),
            '2026-10-27 12:00:00'
        );
    }

    /**
     * No transforma en éxito una excepción del transporte.
     */
    public function testPropagatesUnexpectedTransportFailure(): void {
        $smtp = $this->createMock(SmtpEmailService::class);

        $smtp->expects($this->once())
            ->method('send')
            ->willThrowException(
                new RuntimeException('Fallo del transporte')
            );

        $service = $this->makeService($smtp);

        $this->expectException(RuntimeException::class);

        $service->send(
            'avisos@example.com',
            'https://gastos.example.test',
            'invitado@example.com',
            'Familia',
            str_repeat('a', 64),
            '2026-10-27 12:00:00'
        );
    }

    /**
     * Crea el servicio con transporte simulado.
     *
     * @param SmtpEmailService $smtp Transporte.
     * @return InvitationEmailDeliveryService Servicio.
     */
    private function makeService(
        SmtpEmailService $smtp
    ): InvitationEmailDeliveryService {
        return new class($smtp) extends InvitationEmailDeliveryService {
            /**
             * @param SmtpEmailService $smtp Transporte simulado.
             */
            public function __construct(
                private SmtpEmailService $smtp
            ) {}

            /**
             * Devuelve el generador real.
             *
             * @return InvitationEmailContentService Servicio.
             */
            protected function getContentService(): InvitationEmailContentService {
                return new InvitationEmailContentService();
            }

            /**
             * Devuelve el transporte simulado.
             *
             * @return SmtpEmailService Servicio.
             */
            protected function getSmtpService(): SmtpEmailService {
                return $this->smtp;
            }
        };
    }
}

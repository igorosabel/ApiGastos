<?php declare(strict_types=1);

use Osumi\OsumiFramework\App\Model\SpaceInvitation;
use Osumi\OsumiFramework\App\Service\Email\InvitationEmailDeliveryService;
use Osumi\OsumiFramework\App\Service\Space\SpaceInvitationNotificationService;
use Osumi\OsumiFramework\App\Service\Space\SpaceInvitationService;
use PHPUnit\Framework\TestCase;

final class SpaceInvitationNotificationServiceTest extends TestCase {
    /**
     * Comprueba que la creación termina enviando el correo.
     */
    public function testCreateAndSend(): void {
        $invitation_service = $this->createMock(
            SpaceInvitationService::class
        );
        $delivery = $this->createMock(
            InvitationEmailDeliveryService::class
        );

        $invitation_service->expects($this->once())
            ->method('create')
            ->with(8, 1, 'invitado@example.com')
            ->willReturn($this->issuedInvitation());

        $delivery->expects($this->once())
            ->method('send')
            ->with(
                'avisos@example.com',
                'https://gastos.example.test',
                'invitado@example.com',
                'Familia',
                str_repeat('a', 64),
                '2026-10-27 12:00:00'
            )
            ->willReturn(true);

        $service = $this->makeService(
            $invitation_service,
            $delivery
        );

        $result = $service->createAndSend(
            8,
            1,
            'invitado@example.com'
        );

        $this->assertTrue($result['email_sent']);
        $this->assertSame(str_repeat('a', 64), $result['token']);
        $this->assertSame(61, $result['invitation']->id);
    }

    /**
     * Un fallo SMTP conserva la invitación y su token.
     */
    public function testSmtpFailureKeepsInvitation(): void {
        $invitation_service = $this->createMock(
            SpaceInvitationService::class
        );
        $delivery = $this->createMock(
            InvitationEmailDeliveryService::class
        );

        $invitation_service->expects($this->once())
            ->method('create')
            ->willReturn($this->issuedInvitation());

        $delivery->expects($this->once())
            ->method('send')
            ->willReturn(false);

        $service = $this->makeService(
            $invitation_service,
            $delivery
        );

        $result = $service->createAndSend(
            8,
            1,
            'invitado@example.com'
        );

        $this->assertFalse($result['email_sent']);
        $this->assertSame(61, $result['invitation']->id);
        $this->assertSame(str_repeat('a', 64), $result['token']);
    }

    /**
     * Una excepción SMTP tampoco destruye el resultado.
     */
    public function testSmtpExceptionKeepsInvitation(): void {
        $invitation_service = $this->createMock(
            SpaceInvitationService::class
        );
        $delivery = $this->createMock(
            InvitationEmailDeliveryService::class
        );

        $invitation_service->expects($this->once())
            ->method('create')
            ->willReturn($this->issuedInvitation());

        $delivery->expects($this->once())
            ->method('send')
            ->willThrowException(
                new RuntimeException('Error SMTP')
            );

        $service = $this->makeService(
            $invitation_service,
            $delivery
        );

        $result = $service->createAndSend(
            8,
            1,
            'invitado@example.com'
        );

        $this->assertFalse($result['email_sent']);
        $this->assertSame(61, $result['invitation']->id);
    }

    /**
     * El reenvío utiliza la operación de renovación.
     */
    public function testResendAndSend(): void {
        $invitation_service = $this->createMock(
            SpaceInvitationService::class
        );
        $delivery = $this->createMock(
            InvitationEmailDeliveryService::class
        );

        $invitation_service->expects($this->once())
            ->method('resend')
            ->with(61, 1)
            ->willReturn($this->issuedInvitation());

        $delivery->expects($this->once())
            ->method('send')
            ->willReturn(true);

        $service = $this->makeService(
            $invitation_service,
            $delivery
        );

        $result = $service->resendAndSend(61, 1);

        $this->assertTrue($result['email_sent']);
        $this->assertSame(61, $result['invitation']->id);
    }

    /**
     * Un fallo de persistencia no debe provocar envío.
     */
    public function testDatabaseFailureDoesNotSend(): void {
        $invitation_service = $this->createMock(
            SpaceInvitationService::class
        );
        $delivery = $this->createMock(
            InvitationEmailDeliveryService::class
        );

        $invitation_service->expects($this->once())
            ->method('create')
            ->willThrowException(
                new RuntimeException('Error de persistencia')
            );

        $delivery->expects($this->never())
            ->method('send');

        $service = $this->makeService(
            $invitation_service,
            $delivery
        );

        $this->expectException(RuntimeException::class);

        $service->createAndSend(
            8,
            1,
            'invitado@example.com'
        );
    }

    /**
     * Genera datos simulados de una invitación creada.
     *
     * @return array{invitation: SpaceInvitation, token: string, space_name: string}
     */
    private function issuedInvitation(): array {
        $invitation = new SpaceInvitation();

        $invitation->id = 61;
        $invitation->id_space = 8;
        $invitation->email = 'invitado@example.com';
        $invitation->expires_at = '2026-10-27 12:00:00';

        return [
            'invitation' => $invitation,
            'token' => str_repeat('a', 64),
            'space_name' => 'Familia'
        ];
    }

    /**
     * Prepara el servicio con dependencias simuladas.
     *
     * @param SpaceInvitationService $invitation_service Servicio de persistencia.
     * @param InvitationEmailDeliveryService $delivery Transporte.
     * @return SpaceInvitationNotificationService Servicio.
     */
    private function makeService(
        SpaceInvitationService $invitation_service,
        InvitationEmailDeliveryService $delivery
    ): SpaceInvitationNotificationService {
        return new class(
            $invitation_service,
            $delivery
        ) extends SpaceInvitationNotificationService {
            /**
             * @param SpaceInvitationService $invitation_service Persistencia.
             * @param InvitationEmailDeliveryService $delivery Envío.
             */
            public function __construct(
                private SpaceInvitationService $invitation_service,
                private InvitationEmailDeliveryService $delivery
            ) {}

            /**
             * @return SpaceInvitationService Servicio simulado.
             */
            protected function getInvitationService(): SpaceInvitationService {
                return $this->invitation_service;
            }

            /**
             * @return InvitationEmailDeliveryService Servicio simulado.
             */
            protected function getDeliveryService(): InvitationEmailDeliveryService {
                return $this->delivery;
            }

            /**
             * @return array{from: string, frontend_url: string}
             */
            protected function getMailSettings(): array {
                return [
                    'from' => 'avisos@example.com',
                    'frontend_url' => 'https://gastos.example.test'
                ];
            }

            /**
             * Evita escribir logs reales en las pruebas unitarias.
             *
             * @param int $invitation_id Identificador de invitación.
             * @param string $reason Código de diagnóstico.
             * @return void
             */
            protected function logFailure(
                int $invitation_id,
                string $reason
            ): void {
                // Sin efectos externos.
            }
        };
    }
}

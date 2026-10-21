<?php declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Service\Space;

use Throwable;
use Osumi\OsumiFramework\App\Model\SpaceInvitation;
use Osumi\OsumiFramework\App\Service\Email\InvitationEmailDeliveryService;
use Osumi\OsumiFramework\Core\OService;
use Osumi\OsumiFramework\Log\OLog;

class SpaceInvitationNotificationService extends OService {
    /**
     * Crea la invitación y después intenta enviarla.
     *
     * @param int $space_id Espacio compartido.
     * @param int $user_id Propietario autenticado.
     * @param string $email Destinatario.
     * @return array{invitation: SpaceInvitation, token: string, email_sent: bool}
     */
    public function createAndSend(
        int $space_id,
        int $user_id,
        string $email
    ): array {
        $result = $this->getInvitationService()->create(
            $space_id,
            $user_id,
            $email
        );

        return $this->notify($result);
    }

    /**
     * Renueva una invitación pendiente y la envía.
     *
     * @param int $invitation_id Identificador.
     * @param int $user_id Propietario autenticado.
     * @return array{invitation: SpaceInvitation, token: string, email_sent: bool}
     */
    public function resendAndSend(
        int $invitation_id,
        int $user_id
    ): array {
        $result = $this->getInvitationService()->resend(
            $invitation_id,
            $user_id
        );

        return $this->notify($result);
    }

    /**
     * Envía una invitación ya confirmada en la BD.
     *
     * Cualquier fallo SMTP conserva la invitación
     * y devuelve email_sent=false junto al token.
     *
     * @param array{invitation: SpaceInvitation, token: string, space_name: string} $result Datos confirmados.
     * @return array{invitation: SpaceInvitation, token: string, email_sent: bool}
     */
    private function notify(array $result): array {
        $sent = false;
        $invitation_id = (int) $result['invitation']->id;

        try {
            $settings = $this->getMailSettings();

            $sent = $this->getDeliveryService()->send(
                $settings['from'],
                $settings['frontend_url'],
                (string) $result['invitation']->email,
                $result['space_name'],
                $result['token'],
                (string) $result['invitation']->expires_at
            );
            if (!$sent) {
                $this->logFailure(
                    $invitation_id,
                    'delivery_not_confirmed'
                );
            }
        }
        catch (Throwable $e) {
            $sent = false;

            $this->logFailure(
                $invitation_id,
                'exception_' . $e::class
            );
        }

        return [
            'invitation' => $result['invitation'],
            'token' => $result['token'],
            'email_sent' => $sent
        ];
    }

    /**
     * Registra un fallo de notificación sin información sensible.
     *
     * @param int $invitation_id Identificador de invitación.
     * @param string $reason Código interno del fallo.
     * @return void
     */
    protected function logFailure(
        int $invitation_id,
        string $reason
    ): void {
        $entry = sprintf(
            'invitation_email_failed invitation_id=%d reason=%s',
            $invitation_id,
            $reason
        );

        try {
            $logger = new OLog('SpaceInvitationNotificationService');

            if (!$logger->error($entry)) {
                error_log($entry);
            }
        }
        catch (Throwable) {
            error_log($entry);
        }
    }

    /**
     * Servicio responsable de persistencia y permisos.
     *
     * @return SpaceInvitationService Servicio.
     */
    protected function getInvitationService(): SpaceInvitationService {
        return inject(SpaceInvitationService::class);
    }

    /**
     * Servicio responsable del envío SMTP.
     *
     * @return InvitationEmailDeliveryService Servicio.
     */
    protected function getDeliveryService(): InvitationEmailDeliveryService {
        return inject(InvitationEmailDeliveryService::class);
    }

    /**
     * Obtiene exclusivamente configuración del servidor.
     *
     * @return array{from: string, frontend_url: string}
     */
    protected function getMailSettings(): array {
        $frontend_url = $this->getConfig()->getExtra('frontend_url');

        return [
            'from' => $this->getConfig()->getMailingFrom(),
            'frontend_url' => is_string($frontend_url)
                ? $frontend_url
                : ''
        ];
    }
}

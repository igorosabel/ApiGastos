<?php declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Service\Email;

use Osumi\OsumiFramework\Core\OService;

class InvitationEmailDeliveryService extends OService {
    /**
     * Prepara y envía una invitación por correo.
     *
     * @param string $from Remitente configurado.
     * @param string $frontend_url URL pública del frontend.
     * @param string $recipient Destinatario.
     * @param string $space_name Nombre del espacio.
     * @param string $token Token original.
     * @param string $expires_at Fecha de caducidad.
     * @return bool True si SMTP acepta el correo.
     */
    public function send(
        string $from,
        string $frontend_url,
        string $recipient,
        string $space_name,
        string $token,
        string $expires_at
    ): bool {
        $content = $this->getContentService()->build(
            $frontend_url,
            $space_name,
            $token,
            $expires_at
        );

        return $this->getSmtpService()->send(
            $from,
            $recipient,
            $content['subject'],
            $content['html']
        );
    }

    /**
     * Obtiene el generador del contenido.
     *
     * @return InvitationEmailContentService Servicio.
     */
    protected function getContentService(): InvitationEmailContentService {
        return inject(InvitationEmailContentService::class);
    }

    /**
     * Obtiene el servicio de transporte SMTP.
     *
     * @return SmtpEmailService Servicio.
     */
    protected function getSmtpService(): SmtpEmailService {
        return inject(SmtpEmailService::class);
    }
}

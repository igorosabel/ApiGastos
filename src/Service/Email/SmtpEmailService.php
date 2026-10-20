<?php declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Service\Email;

use InvalidArgumentException;
use Osumi\OsumiFramework\Core\OService;
use Osumi\OsumiFramework\Plugins\OEmailSMTP;

class SmtpEmailService extends OService {
    /**
     * Envía un mensaje HTML a un único destinatario.
     *
     * @param string $from Dirección del remitente.
     * @param string $recipient Correo del destinatario.
     * @param string $subject Asunto del mensaje.
     * @param string $html Contenido HTML.
     *
     * @return bool True si el servidor SMTP ha aceptado el mensaje.
     */
    public function send(
        string $from,
        string $recipient,
        string $subject,
        string $html
    ): bool {
        $from = trim($from);
        $recipient = trim($recipient);
        $subject = trim($subject);

        if (
            filter_var($from, FILTER_VALIDATE_EMAIL) === false ||
            filter_var($recipient, FILTER_VALIDATE_EMAIL) === false ||
            $subject === '' ||
            trim($html) === ''
        ) {
            throw new InvalidArgumentException(
                'Los datos del correo no son válidos.'
            );
        }

        $mailer = $this->createMailer();

        $mailer->setFrom($from, 'Gastos');
        $mailer->addRecipient($recipient);
        $mailer->setSubject($subject);
        $mailer->setMessage($html);

        $result = $mailer->send();

        return (
            is_array($result) &&
            ($result['status'] ?? null) === 'ok' &&
            in_array(
                $recipient,
                $mailer->getResultOk(),
                true
            )
        );
    }

    /**
     * Crea el cliente SMTP configurado por OFW.
     *
     * El método permite sustituir el transporte
     * en las pruebas sin contactar con SMTP.
     *
     * @return OEmailSMTP Cliente de correo.
     */
    protected function createMailer(): OEmailSMTP {
        return new OEmailSMTP();
    }
}

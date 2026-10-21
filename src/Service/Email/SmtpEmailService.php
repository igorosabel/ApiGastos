<?php declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Service\Email;

use InvalidArgumentException;
use Throwable;
use Osumi\OsumiFramework\Core\OService;
use Osumi\OsumiFramework\Plugins\OEmailSMTP;
use Osumi\OsumiFramework\Log\OLog;

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

        $sent = (
            is_array($result) &&
            ($result['status'] ?? null) === 'ok' &&
            in_array(
                $recipient,
                $mailer->getResultOk(),
                true
            )
        );

        if (!$sent) {
            $this->logFailure($this->classifyFailure($result));
        }

        return $sent;
    }

    /**
     * Clasifica un fallo sin incluir el mensaje bruto de SMTP.
     *
     * @param mixed $result Resultado del plugin.
     * @return string Código seguro para diagnóstico.
     */
    private function classifyFailure(mixed $result): string {
        if (!is_array($result)) {
            return 'invalid_plugin_result';
        }

        if (($result['status'] ?? null) === 'ok') {
            return 'recipient_not_confirmed';
        }

        $detail = $result['mens'] ?? '';
        $message = is_string($detail)
            ? strtolower($detail)
            : '';

        if (
            str_contains($message, 'authenticat') ||
            str_contains($message, '535 ')
        ) {
            return 'authentication_failed';
        }

        if (
            str_contains($message, 'certificate') ||
            str_contains($message, 'starttls') ||
            str_contains($message, 'tls') ||
            str_contains($message, 'ssl')
        ) {
            return 'tls_failed';
        }

        if (
            str_contains($message, 'connect') ||
            str_contains($message, 'timed out') ||
            str_contains($message, 'connection refused')
        ) {
            return 'connection_failed';
        }

        if (
            str_contains($message, 'recipient') ||
            str_contains($message, 'relay') ||
            str_contains($message, '550 ')
        ) {
            return 'recipient_or_relay_rejected';
        }

        return 'smtp_send_failed';
    }

    /**
     * Registra únicamente una categoría segura.
     *
     * @param string $reason Motivo clasificado.
     * @return void
     */
    protected function logFailure(string $reason): void {
        $entry = 'smtp_invitation_failed reason=' . $reason;

        try {
            $logger = new OLog('SmtpEmailService');

            if (!$logger->error($entry)) {
                error_log($entry);
            }
        }
        catch (Throwable) {
            error_log($entry);
        }
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

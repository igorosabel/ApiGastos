<?php declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Service\Recurring;

use DateTimeImmutable;
use DomainException;
use InvalidArgumentException;
use Osumi\OsumiFramework\Core\OService;
use Osumi\OsumiFramework\ORM\ODB;
use PDO;

class SpaceOccurrenceService extends OService {
    /**
     * Genera las ocurrencias de las plantillas activas de un
     * espacio para un mes concreto.
     *
     * @param int $space_id Espacio contable.
     * @param int $user_id Usuario autenticado.
     * @param string $period Primer día del mes.
     * @return array{
     *     created: int,
     *     existing: int,
     *     skipped: int,
     *     total: int
     * }
     */
    public function generate(
        int $space_id,
        int $user_id,
        string $period
    ): array {
        if ($space_id <= 0 || $user_id <= 0) {
            throw new InvalidArgumentException(
                'Los identificadores no son válidos.'
            );
        }

        $month = DateTimeImmutable::createFromFormat(
            '!Y-m-d',
            $period
        );

        if (
            $month === false ||
            $month->format('Y-m-d') !== $period ||
            $month->format('d') !== '01'
        ) {
            throw new InvalidArgumentException(
                'El periodo debe ser el primer día de un mes válido.'
            );
        }

        $db = ODB::getInstance();

        // Autorización inicial antes de acceder al catálogo.
        // La generación individual volverá a comprobarla
        // dentro de su propia transacción.
        $stmt = $db->prepare(
            'SELECT id FROM `space_member`
             WHERE id_space = :space_id
               AND id_user = :user_id
               AND left_at IS NULL
             LIMIT 1'
        );

        $stmt->execute([
            'space_id' => $space_id,
            'user_id' => $user_id
        ]);

        if ($stmt->fetch(PDO::FETCH_ASSOC) === false) {
            throw new DomainException(
                'No tienes acceso a este espacio.'
            );
        }

        // Fotografía de los IDs activos al iniciar el lote.
        // Las escrituras posteriores se validan en generate().
        $stmt = $db->prepare(
            'SELECT id, day_of_month, start_date,
                    end_date, created_at
             FROM `recurring_transaction`
             WHERE id_space = :space_id
               AND active = 1
             ORDER BY id ASC'
        );

        $stmt->execute(['space_id' => $space_id]);
        $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $result = [
            'created' => 0,
            'existing' => 0,
            'skipped' => 0,
            'total' => count($templates)
        ];

        $occurrence_service = new OccurrenceService();

        foreach ($templates as $template) {
            $recurring_id = (int) $template['id'];

            // Si ya existe una ocurrencia, tiene prioridad
            // sobre los límites actuales de la plantilla.
            // Puede estar pendiente, confirmada u omitida.
            $stmt = $db->prepare(
                'SELECT id FROM `recurring_occurrence`
                 WHERE id_recurring_transaction = :recurring_id
                   AND period = :period
                 LIMIT 1'
            );

            $stmt->execute([
                'recurring_id' => $recurring_id,
                'period' => $period
            ]);

            if ($stmt->fetch(PDO::FETCH_ASSOC) !== false) {
                // La llamada individual realiza la comprobación
                // definitiva bajo el bloqueo correspondiente.
                $generated = $occurrence_service->generate(
                    $space_id,
                    $user_id,
                    $recurring_id,
                    $period
                );

                if ($generated['created']) {
                    $result['created']++;
                }
                else {
                    $result['existing']++;
                }

                continue;
            }

            // Filtrar únicamente los meses inequívocamente
            // incompatibles. La decisión definitiva de crear
            // se mantiene en OccurrenceService.
            $creation_month = substr(
                (string) $template['created_at'], 0, 7
            ) . '-01';

            $start_month = substr(
                (string) $template['start_date'], 0, 7
            ) . '-01';

            $day = min(
                (int) $template['day_of_month'],
                (int) $month->format('t')
            );

            $scheduled_date = $month->format('Y-m-') .
                str_pad((string) $day, 2, '0', STR_PAD_LEFT);

            if (
                $period < $creation_month ||
                $period < $start_month ||
                $scheduled_date < $template['start_date'] ||
                (
                    $template['end_date'] !== null &&
                    $scheduled_date > $template['end_date']
                )
            ) {
                $result['skipped']++;
                continue;
            }

            // Revalidación y persistencia bajo bloqueo.
            $generated = $occurrence_service->generate(
                $space_id,
                $user_id,
                $recurring_id,
                $period
            );

            if ($generated['created']) {
                $result['created']++;
            }
            else {
                $result['existing']++;
            }
        }

        return $result;
    }
}

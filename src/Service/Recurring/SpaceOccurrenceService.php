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
     * Genera las ocurrencias del mes para las plantillas
     * que estaban activas al comenzar la consulta.
     *
     * Cada plantilla se procesa independientemente.
     * La elegibilidad se comprueba dentro de
     * OccurrenceService, bajo sus bloqueos habituales.
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

        // Comprobación inicial de autorización.
        // OccurrenceService la repite dentro de su
        // transacción para cada plantilla.
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

        // Seleccionar únicamente los identificadores.
        // No usar fechas o estados de esta consulta
        // para decidir si una mensualidad es válida.
        $stmt = $db->prepare(
            'SELECT id
             FROM `recurring_transaction`
             WHERE id_space = :space_id
               AND active = 1
             ORDER BY id ASC'
        );

        $stmt->execute(['space_id' => $space_id]);

        $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $result = [
            'created' => 0,
            'existing' => 0,
            'skipped' => 0,
            'total' => count($ids)
        ];

        $occurrence_service = new OccurrenceService();

        foreach ($ids as $id) {
            try {
                // La generación individual comprueba
                // las fechas y el estado actual de la
                // plantilla bajo los bloqueos de OFW.
                $generated = $occurrence_service->generate(
                    $space_id,
                    $user_id,
                    (int) $id,
                    $period
                );
            }
            catch (OccurrenceNotEligibleException) {
                // Una plantilla puede haber sido editada
                // o desactivada después del listado.
                // No debe interrumpir el resto del lote.
                $result['skipped']++;
                continue;
            }

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

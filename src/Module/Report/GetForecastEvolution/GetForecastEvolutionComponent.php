<?php declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Module\Report\GetForecastEvolution;

use DomainException;
use InvalidArgumentException;
use Osumi\OsumiFramework\App\Component\Model\ForecastEvolution\ForecastEvolutionComponent;
use Osumi\OsumiFramework\App\Component\Model\MonthlyForecastList\MonthlyForecastListComponent;
use Osumi\OsumiFramework\App\DTO\Report\GetReportEvolutionDTO;
use Osumi\OsumiFramework\App\Service\Report\ReportService;
use Osumi\OsumiFramework\Core\OComponent;
use Osumi\OsumiFramework\Core\OMiddleware;

class GetForecastEvolutionComponent extends OComponent {
    private ReportService $report_service;

    public bool $success = false;
    public ?ForecastEvolutionComponent $report = null;
    public ?string $error = null;
    public ?string $message = null;

    /**
     * Inicializa el servicio de informes.
     */
    public function __construct() {
        parent::__construct();
        $this->report_service = inject(ReportService::class);
    }

    /**
     * Consulta la evolución de previsiones mensuales.
     *
     * @param GetReportEvolutionDTO $dto Petición autenticada.
     * @return void
     */
    public function run(GetReportEvolutionDTO $dto): void {
        if (
            !$dto->isValid() ||
            $dto->spaceId === null ||
            $dto->monthFrom === null ||
            $dto->monthTo === null ||
            $dto->idUser === null
        ) {
            $this->error = 'validation_error';
            $this->message = 'Faltan datos obligatorios.';
            OMiddleware::setStatusCode(400);
            return;
        }

        try {
            $result = $this->report_service->getForecastEvolution(
                $dto->spaceId,
                $dto->idUser,
                $dto->monthFrom,
                $dto->monthTo
            );

            $this->report = new ForecastEvolutionComponent([
                'space_id' => $result['space_id'],
                'month_from' => $result['month_from'],
                'month_to' => $result['month_to'],
                'months' => new MonthlyForecastListComponent([
                    'list' => $result['months']
                ])
            ]);

            $this->success = true;
        }
        catch (InvalidArgumentException $e) {
            $this->error = 'validation_error';
            $this->message = $e->getMessage();
            OMiddleware::setStatusCode(400);
        }
        catch (DomainException $e) {
            $this->error = 'forbidden';
            $this->message = $e->getMessage();
            OMiddleware::setStatusCode(403);
        }
    }
}

<?php declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Module\Report\GetMonthlyForecast;

use DomainException;
use InvalidArgumentException;
use Osumi\OsumiFramework\App\Component\Model\MonthlyForecast\MonthlyForecastComponent;
use Osumi\OsumiFramework\App\Component\Model\MonthlyReport\MonthlyReportComponent;
use Osumi\OsumiFramework\App\DTO\Report\GetMonthlyReportDTO;
use Osumi\OsumiFramework\App\Service\Report\ReportService;
use Osumi\OsumiFramework\Core\OComponent;
use Osumi\OsumiFramework\Core\OMiddleware;

class GetMonthlyForecastComponent extends OComponent {
    private ReportService $report_service;

    public bool $success = false;
    public ?MonthlyForecastComponent $report = null;
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
     * Obtiene la previsión de un mes.
     *
     * @param GetMonthlyReportDTO $dto Petición autenticada.
     * @return void
     */
    public function run(GetMonthlyReportDTO $dto): void {
        if (
            !$dto->isValid() ||
            $dto->spaceId === null ||
            $dto->month === null ||
            $dto->idUser === null
        ) {
            $this->error = 'validation_error';
            $this->message = 'Faltan datos obligatorios.';
            OMiddleware::setStatusCode(400);
            return;
        }

        try {
            $result = $this->report_service->getMonthlyForecast(
                $dto->spaceId,
                $dto->idUser,
                $dto->month
            );

            $this->report = new MonthlyForecastComponent([
                'space_id' => $result['space_id'],
                'month' => $result['month'],
                'actual' => new MonthlyReportComponent(
                    $result['actual']
                ),
                'pending_income' => $result['pending_income'],
                'pending_expense' => $result['pending_expense'],
                'pending_balance' => $result['pending_balance'],
                'pending_income_count' => (
                    $result['pending_income_count']
                ),
                'pending_expense_count' => (
                    $result['pending_expense_count']
                ),
                'pending_occurrence_count' => (
                    $result['pending_occurrence_count']
                ),
                'projected_income' => $result['projected_income'],
                'projected_expense' => $result['projected_expense'],
                'projected_balance' => $result['projected_balance']
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

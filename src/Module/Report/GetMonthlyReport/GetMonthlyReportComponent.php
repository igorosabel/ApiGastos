<?php declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Module\Report\GetMonthlyReport;

use DomainException;
use InvalidArgumentException;
use Osumi\OsumiFramework\App\Component\Model\MonthlyReport\MonthlyReportComponent;
use Osumi\OsumiFramework\App\DTO\Report\GetMonthlyReportDTO;
use Osumi\OsumiFramework\App\Service\Report\ReportService;
use Osumi\OsumiFramework\Core\OComponent;
use Osumi\OsumiFramework\Core\OMiddleware;

class GetMonthlyReportComponent extends OComponent {
    private ReportService $report_service;

    public bool $success = false;
    public ?MonthlyReportComponent $report = null;
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
     * Recupera el informe real de un mes.
     *
     * @param GetMonthlyReportDTO $dto Datos autenticados.
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
            $result = $this->report_service->getMonthly(
                $dto->spaceId,
                $dto->idUser,
                $dto->month
            );

            $this->report = new MonthlyReportComponent($result);
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

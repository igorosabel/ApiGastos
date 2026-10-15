<?php declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Module\Report\GetMonthlyCategoryReport;

use DomainException;
use InvalidArgumentException;
use Osumi\OsumiFramework\App\Component\Model\CategoryReportList\CategoryReportListComponent;
use Osumi\OsumiFramework\App\Component\Model\MonthlyCategoryReport\MonthlyCategoryReportComponent;
use Osumi\OsumiFramework\App\DTO\Report\GetMonthlyReportDTO;
use Osumi\OsumiFramework\App\Service\Report\ReportService;
use Osumi\OsumiFramework\Core\OComponent;
use Osumi\OsumiFramework\Core\OMiddleware;

class GetMonthlyCategoryReportComponent extends OComponent {
    private ReportService $report_service;

    public bool $success = false;
    public ?MonthlyCategoryReportComponent $report = null;
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
     * Obtiene los totales directos por categoría.
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
            $result = $this->report_service->getMonthlyByCategory(
                $dto->spaceId,
                $dto->idUser,
                $dto->month
            );

            $this->report = new MonthlyCategoryReportComponent([
                'space_id' => $result['space_id'],
                'month' => $result['month'],
                'categories' => new CategoryReportListComponent([
                    'list' => $result['categories']
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

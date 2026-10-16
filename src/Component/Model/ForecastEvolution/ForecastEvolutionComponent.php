<?php declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Component\Model\ForecastEvolution;

use Osumi\OsumiFramework\App\Component\Model\MonthlyForecastList\MonthlyForecastListComponent;
use Osumi\OsumiFramework\Core\OComponent;

class ForecastEvolutionComponent extends OComponent {
    public int $space_id = 0;
    public string $month_from = '';
    public string $month_to = '';
    public ?MonthlyForecastListComponent $months = null;
}

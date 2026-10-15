<?php declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Component\Model\CategoryReport;

use Osumi\OsumiFramework\Core\OComponent;

class CategoryReportComponent extends OComponent {
    public int $category_id = 0;
    public ?int $parent_id = null;
    public string $name = '';
    public string $type = '';
    public string $amount = '0.00';
    public int $transaction_count = 0;
}

<?php declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Component\Model\Recurring;

use Osumi\OsumiFramework\App\Model\RecurringTransaction;
use Osumi\OsumiFramework\Core\OComponent;

class RecurringComponent extends OComponent {
    public ?RecurringTransaction $recurring = null;
}

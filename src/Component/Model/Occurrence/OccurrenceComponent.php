<?php declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Component\Model\Occurrence;

use Osumi\OsumiFramework\App\Model\RecurringOccurrence;
use Osumi\OsumiFramework\Core\OComponent;

class OccurrenceComponent extends OComponent {
    public ?RecurringOccurrence $occurrence = null;
}

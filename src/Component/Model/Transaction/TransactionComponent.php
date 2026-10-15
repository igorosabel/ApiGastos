<?php declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Component\Model\Transaction;

use Osumi\OsumiFramework\App\Model\FinancialTransaction;
use Osumi\OsumiFramework\Core\OComponent;

class TransactionComponent extends OComponent {
    public ?FinancialTransaction $transaction = null;
}

<?php declare(strict_types=1);

use Osumi\OsumiFramework\App\Component\Model\Transaction\TransactionComponent;

foreach ($list as $i => $transaction) {
    $component = new TransactionComponent([
        'transaction' => $transaction
    ]);

    echo strval($component);

    if ($i < count($list) - 1) {
        echo ',';
    }
}

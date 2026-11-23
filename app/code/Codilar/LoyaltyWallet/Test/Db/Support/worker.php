<?php
/**
 * Child process for ConcurrencyDbTest. Usage: php worker.php <action> <startAtMicrotime> <args...>
 * Waits until the shared start time so all workers hit the database together, then prints one line of JSON.
 */
declare(strict_types=1);

use Codilar\LoyaltyWallet\Api\Data\WalletLedgerEntryInterface as W;
use Codilar\LoyaltyWallet\Api\PointsManagementInterface;
use Codilar\LoyaltyWallet\Model\Points\ExpiryProcessor;
use Codilar\LoyaltyWallet\Model\Wallet\WalletMovement;
use Codilar\LoyaltyWallet\Model\Wallet\WalletService;
use Magento\Framework\App\Bootstrap;

require __DIR__ . '/../../../../../../../app/bootstrap.php';

[, $action, $startAt] = $argv;
$args = array_slice($argv, 3);
$om = Bootstrap::create(BP, $_SERVER)->getObjectManager();
$om->get(\Magento\Framework\App\State::class)->setAreaCode('frontend');

while (microtime(true) < (float)$startAt) {
    usleep(200);
}
try {
    switch ($action) {
        case 'debit': // customerId amount key
            $om->get(WalletService::class)->debit(new WalletMovement(
                (int)$args[0], 'cc@example.com', W::TYPE_DEBIT_REDEMPTION, $args[1], 'race', $args[2], null, 'CC-' . $args[2]
            ));
            $result = ['ok' => true];
            break;
        case 'convert': // customerId points requestKey
            $r = $om->get(PointsManagementInterface::class)->convertToWallet(
                (int)$args[0], 'cc@example.com', (int)$args[1], $args[2]
            );
            $result = ['ok' => true, 'replayed' => $r->replayed, 'wallet' => $r->walletAmount];
            break;
        case 'expire':
            $r = $om->get(ExpiryProcessor::class)->run();
            $result = ['ok' => true, 'skipped' => $r->skipped, 'points' => $r->pointsExpired, 'lots' => $r->lotsProcessed];
            break;
        default:
            $result = ['ok' => false, 'error' => 'unknown action'];
    }
} catch (\Throwable $e) {
    $result = ['ok' => false, 'error' => $e->getMessage()];
}
echo json_encode($result) . "\n";

<?php
declare(strict_types=1);

namespace Codilar\LoyaltyWallet\Model\Points;

use Codilar\LoyaltyWallet\Api\Data\PointsLedgerEntryInterface as P;
use Codilar\LoyaltyWallet\Api\Data\WalletLedgerEntryInterface as W;
use Codilar\LoyaltyWallet\Api\PointsLedgerRepositoryInterface;
use Codilar\LoyaltyWallet\Model\Decimal;
use Codilar\LoyaltyWallet\Model\Locking\BalanceLocker;
use Codilar\LoyaltyWallet\Model\Locking\TransactionRunner;
use Codilar\LoyaltyWallet\Model\PointsLedgerFactory;
use Codilar\LoyaltyWallet\Model\Wallet\WalletMovement;
use Codilar\LoyaltyWallet\Model\Wallet\WalletService;
use Magento\Framework\Exception\LocalizedException;

/**
 * Takes back the points an order earned (FR-05, BR-03). For the part being reversed:
 *  1. points still unspent are removed from the lot (REVERSAL ledger entry);
 *  2. points that were already converted to wallet money are clawed back at the value they were converted at;
 *     the clawback stops at a zero wallet and the shortfall is recorded as a write-off, never a negative balance;
 *  3. points that already expired are simply gone and need nothing.
 * Progress is stored per lot as a cumulative "handled" count, so calling this again with the same or a lower target
 * does nothing, and a later, higher target only processes the difference.
 */
class ReversalService
{
    /**
     * @param BalanceLocker $locker
     * @param TransactionRunner $transaction
     * @param LotStore $lots
     * @param PointsLedgerRepositoryInterface $pointsLedger
     * @param PointsLedgerFactory $entryFactory
     * @param WalletService $wallet
     */
    public function __construct(
        private readonly BalanceLocker $locker,
        private readonly TransactionRunner $transaction,
        private readonly LotStore $lots,
        private readonly PointsLedgerRepositoryInterface $pointsLedger,
        private readonly PointsLedgerFactory $entryFactory,
        private readonly WalletService $wallet
    ) {
    }

    /**
     * @param int $customerId
     * @param int $orderId
     * @param string $incrementId
     * @param string $email
     * @param int|null $targetPoints Cumulative points of the order to have reversed; null means all of them
     * @return ReversalResult
     * @throws LocalizedException
     */
    public function reverseTo(
        int $customerId,
        int $orderId,
        string $incrementId,
        string $email,
        ?int $targetPoints
    ): ReversalResult {
        return $this->transaction->run(
            fn (): ReversalResult => $this->reverseLocked($customerId, $orderId, $incrementId, $email, $targetPoints)
        );
    }

    /**
     * @param int $customerId
     * @param int $orderId
     * @param string $incrementId
     * @param string $email
     * @param int|null $targetPoints
     * @return ReversalResult
     * @throws LocalizedException
     */
    private function reverseLocked(
        int $customerId,
        int $orderId,
        string $incrementId,
        string $email,
        ?int $targetPoints
    ): ReversalResult {
        $balances = $this->locker->lock($customerId);
        $lot = $this->lots->lockByOrder($orderId);
        if ($lot === null || $lot['customer_id'] !== $customerId) {
            return new ReversalResult();
        }
        $target = min($targetPoints ?? $lot['points_original'], $lot['points_original']);
        $delta = $target - $lot['points_reversal_handled'];
        if ($delta <= 0) {
            return new ReversalResult();
        }

        $fromLot = min($delta, $lot['points_remaining']);
        $converted = $this->lots->convertedTotals($lot['lot_id']);
        $clawPoints = min($delta - $fromLot, max(0, $converted['points'] - $lot['points_clawed']));
        $key = 'reverse:o' . $orderId . ':t' . $target;

        if ($fromLot > 0) {
            $pointsBalance = $balances['points'] - $fromLot;
            $this->pointsLedger->append($this->entryFactory->create(['data' => [
                P::CUSTOMER_ID => $customerId,
                P::CUSTOMER_EMAIL => $email,
                P::ENTRY_TYPE => P::TYPE_REVERSAL,
                P::POINTS => -$fromLot,
                P::BALANCE_AFTER => $pointsBalance,
                P::LOT_ID => $lot['lot_id'],
                P::ORDER_ID => $orderId,
                P::ORDER_INCREMENT_ID => $incrementId,
                P::REASON => 'Points reversed for order #' . $incrementId,
                P::IDEMPOTENCY_KEY => $key,
            ]]));
            $this->lots->consume($lot['lot_id'], $fromLot);
            $this->locker->setPointsBalance($customerId, $pointsBalance);
        }

        $clawed = '0.0000';
        $writtenOff = '0.0000';
        if ($clawPoints > 0) {
            $value = bcsub(
                $this->valueOf($converted, $lot['points_clawed'] + $clawPoints),
                $this->valueOf($converted, $lot['points_clawed']),
                Decimal::SCALE
            );
            if (Decimal::sign($value) > 0) {
                $result = $this->wallet->clawback(new WalletMovement(
                    $customerId,
                    $email,
                    W::TYPE_CLAWBACK,
                    $value,
                    'Points reversed for order #' . $incrementId . ' after they were converted',
                    $key . ':wallet',
                    $orderId,
                    $incrementId
                ));
                $clawed = $result->clawedBack;
                $writtenOff = $result->writtenOff;
            }
        }
        $this->lots->markReversal($lot['lot_id'], $target, $clawPoints);
        return new ReversalResult($fromLot, $clawPoints, $clawed, $writtenOff);
    }

    /**
     * Wallet value of the first $points converted points of a lot, half-up at 4 places. Working with cumulative
     * values means the pieces of a split reversal always add up to exactly the value that was converted.
     *
     * @param array{points: int, value: string} $converted
     * @param int $points
     * @return string
     */
    private function valueOf(array $converted, int $points): string
    {
        if ($converted['points'] <= 0 || $points <= 0) {
            return '0.0000';
        }
        $exact = bcdiv(bcmul($converted['value'], (string)$points, 8), (string)$converted['points'], 8);
        return bcadd(bcadd($exact, '0.00005', 8), '0', Decimal::SCALE);
    }
}

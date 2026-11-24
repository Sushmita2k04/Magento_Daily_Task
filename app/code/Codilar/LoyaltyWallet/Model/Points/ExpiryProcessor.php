<?php
declare(strict_types=1);

namespace Codilar\LoyaltyWallet\Model\Points;

use Codilar\LoyaltyWallet\Api\Data\PointsLedgerEntryInterface as P;
use Codilar\LoyaltyWallet\Api\PointsLedgerRepositoryInterface;
use Codilar\LoyaltyWallet\Model\Locking\BalanceLocker;
use Codilar\LoyaltyWallet\Model\Locking\TransactionRunner;
use Codilar\LoyaltyWallet\Model\PointsLedgerFactory;
use DateTimeImmutable;
use DateTimeZone;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Lock\LockManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Expires points whose lot is past its expiry date (FR-03).
 *
 * Safe to run repeatedly or concurrently (NFR-03): a run-level lock stops overlapping runs, each lot is closed in
 * its own transaction under the customer's balance lock, an already closed lot is skipped, and the EXPIRY ledger
 * entry carries the unique key "expiry:{lotId}" as a last line of defence.
 */
class ExpiryProcessor
{
    private const LOCK_NAME = 'codilar_loyalty_points_expiry';
    private const BATCH_SIZE = 200;

    /**
     * @param LotStore $lots
     * @param BalanceLocker $locker
     * @param TransactionRunner $transaction
     * @param PointsLedgerRepositoryInterface $ledger
     * @param PointsLedgerFactory $entryFactory
     * @param LockManagerInterface $lockManager
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly LotStore $lots,
        private readonly BalanceLocker $locker,
        private readonly TransactionRunner $transaction,
        private readonly PointsLedgerRepositoryInterface $ledger,
        private readonly PointsLedgerFactory $entryFactory,
        private readonly LockManagerInterface $lockManager,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Number of lots and points an expiry run would process now.
     *
     * @param DateTimeImmutable|null $now
     * @return array{lots: int, points: int}
     */
    public function preview(?DateTimeImmutable $now = null): array
    {
        return $this->lots->summarizeDue($this->format($now));
    }

    /**
     * @param DateTimeImmutable|null $now Defaults to the current time (UTC)
     * @return ExpiryReport
     */
    public function run(?DateTimeImmutable $now = null): ExpiryReport
    {
        if (!$this->lockManager->lock(self::LOCK_NAME, 0)) {
            return new ExpiryReport(0, 0, [], true);
        }
        try {
            return $this->process($this->format($now));
        } finally {
            $this->lockManager->unlock(self::LOCK_NAME);
        }
    }

    /**
     * @param string $now
     * @return ExpiryReport
     */
    private function process(string $now): ExpiryReport
    {
        $lotsProcessed = 0;
        $pointsExpired = 0;
        $failed = [];
        while ($due = $this->lots->findDue($now, self::BATCH_SIZE, $failed)) {
            foreach ($due as $lot) {
                try {
                    $expired = $this->expireLot($lot['lot_id'], $lot['customer_id'], $now);
                    if ($expired !== null) {
                        $lotsProcessed++;
                        $pointsExpired += $expired;
                    }
                } catch (\Throwable $e) {
                    $failed[] = $lot['lot_id'];
                    $this->logger->error(
                        'Loyalty expiry failed for lot ' . $lot['lot_id'] . ': ' . $e->getMessage(),
                        ['exception' => $e]
                    );
                }
            }
        }
        return new ExpiryReport($lotsProcessed, $pointsExpired, $failed);
    }

    /**
     * @param int $lotId
     * @param int $customerId
     * @param string $now
     * @return int|null Points expired, or null when another run had already closed the lot
     */
    private function expireLot(int $lotId, int $customerId, string $now): ?int
    {
        return $this->transaction->run(function () use ($lotId, $customerId, $now): ?int {
            $balances = $this->locker->lock($customerId);
            $lot = $this->lots->lockUnexpired($lotId);
            if ($lot === null) {
                return null;
            }
            $remaining = $lot['points_remaining'];
            if ($remaining > 0) {
                if ($remaining > $balances['points']) {
                    throw new LocalizedException(
                        __('Lot %1 holds more points than the balance; run reconciliation.', $lotId)
                    );
                }
                $balance = $balances['points'] - $remaining;
                $this->ledger->append($this->entryFactory->create(['data' => [
                    P::CUSTOMER_ID => $customerId,
                    P::CUSTOMER_EMAIL => $this->lots->snapshotEmail($lotId),
                    P::ENTRY_TYPE => P::TYPE_EXPIRY,
                    P::POINTS => -$remaining,
                    P::BALANCE_AFTER => $balance,
                    P::LOT_ID => $lotId,
                    P::REASON => 'Loyalty points expired',
                    P::IDEMPOTENCY_KEY => 'expiry:' . $lotId,
                ]]));
                $this->locker->setPointsBalance($customerId, $balance);
            }
            $this->lots->markExpired($lotId, $now);
            return $remaining;
        });
    }

    /**
     * @param DateTimeImmutable|null $now
     * @return string
     */
    private function format(?DateTimeImmutable $now): string
    {
        return ($now ?? new DateTimeImmutable('now', new DateTimeZone('UTC')))->format(AccrualService::DATE_FORMAT);
    }
}

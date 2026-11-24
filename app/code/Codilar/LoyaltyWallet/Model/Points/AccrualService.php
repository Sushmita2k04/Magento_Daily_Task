<?php
declare(strict_types=1);

namespace Codilar\LoyaltyWallet\Model\Points;

use Codilar\LoyaltyWallet\Api\Data\PointsLedgerEntryInterface as E;
use Codilar\LoyaltyWallet\Api\PointsLedgerRepositoryInterface;
use Codilar\LoyaltyWallet\Model\Config;
use Codilar\LoyaltyWallet\Model\Locking\BalanceLocker;
use Codilar\LoyaltyWallet\Model\Locking\TransactionRunner;
use Codilar\LoyaltyWallet\Model\PointsLedgerFactory;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Awards points for an order: one lot, one ACCRUAL ledger entry, one balance update, in one transaction.
 */
class AccrualService
{
    public const DATE_FORMAT = 'Y-m-d H:i:s';

    /**
     * @param AccrualCalculator $calculator
     * @param Config $config
     * @param LotStore $lots
     * @param BalanceLocker $locker
     * @param TransactionRunner $transaction
     * @param PointsLedgerRepositoryInterface $ledger
     * @param PointsLedgerFactory $entryFactory
     */
    public function __construct(
        private readonly AccrualCalculator $calculator,
        private readonly Config $config,
        private readonly LotStore $lots,
        private readonly BalanceLocker $locker,
        private readonly TransactionRunner $transaction,
        private readonly PointsLedgerRepositoryInterface $ledger,
        private readonly PointsLedgerFactory $entryFactory
    ) {
    }

    /**
     * @param int $customerId
     * @param string $email
     * @param int $orderId
     * @param string $orderIncrementId
     * @param string $eligibleAmount
     * @param int|null $storeId
     * @param DateTimeImmutable|null $at Accrual moment; defaults to now (UTC)
     * @return E|null
     */
    public function accrue(
        int $customerId,
        string $email,
        int $orderId,
        string $orderIncrementId,
        string $eligibleAmount,
        ?int $storeId = null,
        ?DateTimeImmutable $at = null
    ): ?E {
        $points = $this->calculator->calculate($eligibleAmount, $storeId);
        if ($points <= 0) {
            return null;
        }
        $at ??= new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $key = 'accrual:' . $orderId;

        return $this->transaction->run(function () use (
            $customerId, $email, $orderId, $orderIncrementId, $points, $storeId, $at, $key
        ): E {
            $balances = $this->locker->lock($customerId);
            $existing = $this->ledger->getByIdempotencyKey($key);
            if ($existing !== null) {
                return $existing;
            }
            $accruedAt = $at->format(self::DATE_FORMAT);
            $expiresAt = $at->modify('+' . $this->config->getExpiryMonths($storeId) . ' months')
                ->format(self::DATE_FORMAT);
            $lotId = $this->lots->create($customerId, $orderId, $points, $accruedAt, $expiresAt);
            $balance = $balances['points'] + $points;

            $entry = $this->ledger->append($this->entryFactory->create(['data' => [
                E::CUSTOMER_ID => $customerId,
                E::CUSTOMER_EMAIL => $email,
                E::ENTRY_TYPE => E::TYPE_ACCRUAL,
                E::POINTS => $points,
                E::BALANCE_AFTER => $balance,
                E::LOT_ID => $lotId,
                E::ORDER_ID => $orderId,
                E::ORDER_INCREMENT_ID => $orderIncrementId,
                E::REASON => 'Points earned on order #' . $orderIncrementId,
                E::ACCRUED_AT => $accruedAt,
                E::EXPIRES_AT => $expiresAt,
                E::IDEMPOTENCY_KEY => $key,
            ]]));
            $this->locker->setPointsBalance($customerId, $balance);
            return $entry;
        });
    }
}

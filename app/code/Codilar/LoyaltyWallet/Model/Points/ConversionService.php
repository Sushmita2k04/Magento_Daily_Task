<?php
declare(strict_types=1);

namespace Codilar\LoyaltyWallet\Model\Points;

use Codilar\LoyaltyWallet\Api\Data\PointsLedgerEntryInterface as P;
use Codilar\LoyaltyWallet\Api\Data\WalletLedgerEntryInterface as W;
use Codilar\LoyaltyWallet\Api\PointsLedgerRepositoryInterface;
use Codilar\LoyaltyWallet\Api\WalletLedgerRepositoryInterface;
use Codilar\LoyaltyWallet\Model\Config;
use Codilar\LoyaltyWallet\Model\Decimal;
use Codilar\LoyaltyWallet\Model\Locking\BalanceLocker;
use Codilar\LoyaltyWallet\Model\Locking\TransactionRunner;
use Codilar\LoyaltyWallet\Model\PointsLedgerFactory;
use Codilar\LoyaltyWallet\Model\WalletLedgerFactory;
use DateTimeImmutable;
use DateTimeZone;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\LocalizedException;

/**
 * Converts points into wallet balance (FR-04). Oldest-expiring lots are used first (FR-03 / FIFO). Points ledger,
 * wallet ledger, lots and both balances change together or not at all. Irreversible by design (BR-01): there is
 * no wallet-to-points path.
 */
class ConversionService
{
    /**
     * @param Config $config
     * @param LotStore $lots
     * @param BalanceLocker $locker
     * @param TransactionRunner $transaction
     * @param PointsLedgerRepositoryInterface $pointsLedger
     * @param WalletLedgerRepositoryInterface $walletLedger
     * @param PointsLedgerFactory $pointsEntryFactory
     * @param WalletLedgerFactory $walletEntryFactory
     * @param ResourceConnection $resource
     */
    public function __construct(
        private readonly Config $config,
        private readonly LotStore $lots,
        private readonly BalanceLocker $locker,
        private readonly TransactionRunner $transaction,
        private readonly PointsLedgerRepositoryInterface $pointsLedger,
        private readonly WalletLedgerRepositoryInterface $walletLedger,
        private readonly PointsLedgerFactory $pointsEntryFactory,
        private readonly WalletLedgerFactory $walletEntryFactory,
        private readonly ResourceConnection $resource
    ) {
    }

    /**
     * @param int $customerId
     * @param string $email
     * @param int $points
     * @param string $requestKey
     * @param DateTimeImmutable|null $at Defaults to now (UTC)
     * @return ConversionResult
     * @throws LocalizedException
     */
    public function convert(
        int $customerId,
        string $email,
        int $points,
        string $requestKey,
        ?DateTimeImmutable $at = null
    ): ConversionResult {
        $minimum = $this->config->getMinConversionPoints();
        if ($points < $minimum) {
            throw new LocalizedException(__('You must convert at least %1 points.', $minimum));
        }
        if (trim($requestKey) === '' || strlen($requestKey) > 100) {
            throw new LocalizedException(__('A request key of 1 to 100 characters is required.'));
        }
        $now = ($at ?? new DateTimeImmutable('now', new DateTimeZone('UTC')))->format(AccrualService::DATE_FORMAT);

        return $this->transaction->run(
            fn (): ConversionResult => $this->convertLocked($customerId, $email, $points, $requestKey, $now)
        );
    }

    /**
     * @param int $customerId
     * @param string $email
     * @param int $points
     * @param string $requestKey
     * @param string $now
     * @return ConversionResult
     * @throws LocalizedException
     */
    private function convertLocked(
        int $customerId,
        string $email,
        int $points,
        string $requestKey,
        string $now
    ): ConversionResult {
        $balances = $this->locker->lock($customerId);
        $walletKey = 'convert:' . $requestKey . ':wallet';

        $existing = $this->walletLedger->getByIdempotencyKey($walletKey);
        if ($existing !== null) {
            return $this->replayed($existing, $balances);
        }
        if ($points > $balances['points']) {
            throw new LocalizedException(__('You only have %1 points.', $balances['points']));
        }

        $rate = $this->config->getPointsPerWalletUnit();
        $allocation = $this->allocate($customerId, $points, $rate, $now);

        $walletBalance = bcadd($balances['wallet'], $allocation['total'], Decimal::SCALE);
        $walletEntry = $this->walletLedger->append($this->walletEntryFactory->create(['data' => [
            W::CUSTOMER_ID => $customerId,
            W::CUSTOMER_EMAIL => $email,
            W::ENTRY_TYPE => W::TYPE_CREDIT_CONVERSION,
            W::AMOUNT => $allocation['total'],
            W::BALANCE_AFTER => $walletBalance,
            W::REASON => sprintf('Converted %d loyalty points', $points),
            W::IDEMPOTENCY_KEY => $walletKey,
        ]]));

        $pointsBalance = $balances['points'];
        foreach ($allocation['rows'] as $row) {
            $pointsBalance -= $row['points'];
            $this->pointsLedger->append($this->pointsEntryFactory->create(['data' => [
                P::CUSTOMER_ID => $customerId,
                P::CUSTOMER_EMAIL => $email,
                P::ENTRY_TYPE => P::TYPE_CONVERSION,
                P::POINTS => -$row['points'],
                P::BALANCE_AFTER => $pointsBalance,
                P::LOT_ID => $row['lot_id'],
                P::WALLET_ENTRY_ID => $walletEntry->getEntryId(),
                P::WALLET_VALUE => $row['value'],
                P::REASON => sprintf('Converted to wallet at %s points per 1.00', $rate),
                P::IDEMPOTENCY_KEY => 'convert:' . $requestKey . ':lot:' . $row['lot_id'],
            ]]));
            $this->lots->consume($row['lot_id'], $row['points']);
        }
        $this->locker->setWalletBalance($customerId, $walletBalance);
        $this->locker->setPointsBalance($customerId, $pointsBalance);

        return new ConversionResult($points, $allocation['total'], $pointsBalance, $walletBalance);
    }

    /**
     * FIFO allocation across lots. The wallet credit is the sum of each lot's value at the rate in force now.
     *
     * @param int $customerId
     * @param int $points
     * @param string $rate
     * @param string $now
     * @return array{total: string, rows: array<int, array{lot_id: int, points: int, value: string}>}
     * @throws LocalizedException
     */
    private function allocate(int $customerId, int $points, string $rate, string $now): array
    {
        $needed = $points;
        $total = '0.0000';
        $rows = [];
        foreach ($this->lots->lockSpendable($customerId, $now) as $lot) {
            if ($needed === 0) {
                break;
            }
            $take = min($lot['points_remaining'], $needed);
            $value = Decimal::divideRounded((string)$take, $rate);
            $rows[] = ['lot_id' => $lot['lot_id'], 'points' => $take, 'value' => $value];
            $total = bcadd($total, $value, Decimal::SCALE);
            $needed -= $take;
        }
        if ($needed > 0) {
            throw new LocalizedException(
                __('Only %1 of those points can be converted right now.', $points - $needed)
            );
        }
        if (Decimal::sign($total) <= 0) {
            throw new LocalizedException(__('That many points is worth less than the smallest wallet amount.'));
        }
        return ['total' => $total, 'rows' => $rows];
    }

    /**
     * @param W $walletEntry
     * @param array{wallet: string, points: int} $balances
     * @return ConversionResult
     */
    private function replayed(W $walletEntry, array $balances): ConversionResult
    {
        $connection = $this->resource->getConnection();
        $converted = (int)$connection->fetchOne(
            $connection->select()
                ->from(
                    $this->resource->getTableName('codilar_loyalty_points_ledger'),
                    [new \Zend_Db_Expr('-SUM(points)')]
                )
                ->where('wallet_entry_id = ?', $walletEntry->getEntryId())
        );
        return new ConversionResult(
            $converted,
            Decimal::normalize($walletEntry->getAmount()),
            $balances['points'],
            $balances['wallet'],
            true
        );
    }
}

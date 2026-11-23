<?php
declare(strict_types=1);

namespace Codilar\LoyaltyWallet\Test\Db;

use Codilar\LoyaltyWallet\Model\Config;
use Codilar\LoyaltyWallet\Model\Points\AccrualCalculator;
use Codilar\LoyaltyWallet\Model\Points\AccrualService;
use Codilar\LoyaltyWallet\Model\Points\ConversionService;
use Codilar\LoyaltyWallet\Model\Points\ExpiryProcessor;
use Codilar\LoyaltyWallet\Model\Points\LotStore;
use DateTimeImmutable;
use DateTimeZone;
use Magento\Framework\Exception\LocalizedException;

/**
 * Stage 2: accrual (AC-01), FIFO conversion (AC-02) and expiry (AC-05). Uses a stubbed Config so the results do not
 * depend on whatever values are set in the dev database: 1 point per 1.00 spent, 10 points per 1.00 of wallet,
 * minimum 5 points, 12 months expiry.
 */
class PointsDbTest extends DbTestCase
{
    private AccrualService $accrual;
    private ConversionService $conversion;
    private ExpiryProcessor $expiry;
    private LotStore $lots;

    protected function setUp(): void
    {
        parent::setUp();
        $config = $this->createStub(Config::class);
        $config->method('getAccrualRate')->willReturn('1.0000');
        $config->method('getPointsPerWalletUnit')->willReturn('10.0000');
        $config->method('getMinConversionPoints')->willReturn(5);
        $config->method('getExpiryMonths')->willReturn(12);
        $this->accrual = $this->om->create(AccrualService::class, [
            'config' => $config, 'calculator' => new AccrualCalculator($config),
        ]);
        $this->conversion = $this->om->create(ConversionService::class, ['config' => $config]);
        $this->expiry = $this->om->get(ExpiryProcessor::class);
        $this->lots = $this->om->get(LotStore::class);
    }

    private function at(string $date): DateTimeImmutable
    {
        return new DateTimeImmutable($date . ' 10:00:00', new DateTimeZone('UTC'));
    }

    /**
     * @return int lot id
     */
    private function earn(int $orderId, string $amount, string $date, int $customer = self::CUSTOMER_A): int
    {
        $entry = $this->accrual->accrue(
            $customer, 'db@test.local', $orderId, '00000' . $orderId, $amount, null, $this->at($date)
        );
        $this->assertNotNull($entry);
        return (int)$entry->getData('lot_id');
    }

    private function pointsBalance(int $customer = self::CUSTOMER_A): int
    {
        return (int)$this->connection->fetchOne(
            'SELECT points_balance FROM ' . $this->table('codilar_loyalty_points_balance') . ' WHERE customer_id = ?',
            [$customer]
        );
    }

    private function walletBalance(int $customer = self::CUSTOMER_A): string
    {
        return (string)$this->connection->fetchOne(
            'SELECT balance FROM ' . $this->table('codilar_wallet_balance') . ' WHERE customer_id = ?',
            [$customer]
        );
    }

    // AC-01 -----------------------------------------------------------------------------------------------------

    public function testAccrualCreatesLotLedgerEntryAndBalance(): void
    {
        $entry = $this->accrual->accrue(
            self::CUSTOMER_A, 'db@test.local', 7001, '000007001', '250.75', null, $this->at('2026-01-31')
        );

        $this->assertSame(250, $entry->getPoints());
        $this->assertSame(250, $entry->getBalanceAfter());
        $this->assertSame('2027-01-31 10:00:00', $entry->getExpiresAt(), 'Expires 12 months after accrual (FR-03)');
        $this->assertSame(250, $this->pointsBalance());
        $this->assertSame(250, $this->lotRemaining((int)$entry->getData('lot_id')));
        $this->assertLedgersReconcile(self::CUSTOMER_A);
    }

    public function testAccrualForTheSameOrderIsIdempotent(): void
    {
        $first = $this->accrual->accrue(self::CUSTOMER_A, 'db@test.local', 7002, '000007002', '100', null);
        $second = $this->accrual->accrue(self::CUSTOMER_A, 'db@test.local', 7002, '000007002', '100', null);

        $this->assertSame($first->getEntryId(), $second->getEntryId());
        $this->assertSame(100, $this->pointsBalance());
        $this->assertSame(1, (int)$this->connection->fetchOne(
            'SELECT COUNT(*) FROM ' . $this->table('codilar_loyalty_points_lot') . ' WHERE order_id = 7002'
        ));
        $this->assertLedgersReconcile(self::CUSTOMER_A);
    }

    public function testOrderThatEarnsNothingWritesNothing(): void
    {
        $this->assertNull($this->accrual->accrue(self::CUSTOMER_A, 'db@test.local', 7003, '000007003', '0.40', null));
        $this->assertSame(0, (int)$this->connection->fetchOne(
            'SELECT COUNT(*) FROM ' . $this->table('codilar_loyalty_points_ledger') . ' WHERE order_id = 7003'
        ));
    }

    // AC-02 -----------------------------------------------------------------------------------------------------

    public function testConversionUsesOldestExpiringPointsFirstAndReconciles(): void
    {
        $lot1 = $this->earn(7101, '100', '2026-01-01');
        $lot2 = $this->earn(7102, '100', '2026-02-01');
        $lot3 = $this->earn(7103, '100', '2026-03-01');

        $result = $this->conversion->convert(
            self::CUSTOMER_A, 'db@test.local', 150, 'req-fifo', $this->at('2026-03-15')
        );

        $this->assertSame('15.0000', $result->walletAmount, '150 points at 10 per 1.00');
        $this->assertSame(150, $result->pointsBalance);
        $this->assertSame('15.0000', $result->walletBalance);
        $this->assertFalse($result->replayed);
        $this->assertSame(0, $this->lotRemaining($lot1), 'Oldest lot is used up first');
        $this->assertSame(50, $this->lotRemaining($lot2));
        $this->assertSame(100, $this->lotRemaining($lot3), 'Newest lot untouched');
        $this->assertSame(150, $this->pointsBalance());
        $this->assertSame('15.0000', $this->walletBalance());
        $this->assertLedgersReconcile(self::CUSTOMER_A);

        $this->assertSame('15.0000', bcadd((string)$this->connection->fetchOne(
            'SELECT SUM(wallet_value) FROM ' . $this->table('codilar_loyalty_points_ledger')
            . " WHERE entry_type = 'CONVERSION' AND customer_id = ?",
            [self::CUSTOMER_A]
        ), '0', 4), 'Per-lot values add up to the wallet credit');
    }

    public function testRepeatingTheSameRequestDoesNotConvertTwice(): void
    {
        $this->earn(7111, '100', '2026-01-01');
        $first = $this->conversion->convert(
            self::CUSTOMER_A, 'db@test.local', 40, 'req-twice', $this->at('2026-02-01')
        );
        $second = $this->conversion->convert(
            self::CUSTOMER_A, 'db@test.local', 40, 'req-twice', $this->at('2026-02-01')
        );

        $this->assertFalse($first->replayed);
        $this->assertTrue($second->replayed);
        $this->assertSame(40, $second->pointsConverted);
        $this->assertSame('4.0000', $second->walletAmount);
        $this->assertSame(60, $this->pointsBalance());
        $this->assertSame('4.0000', $this->walletBalance());
        $this->assertLedgersReconcile(self::CUSTOMER_A);
    }

    public function testCannotConvertMoreThanTheBalanceAndNothingChanges(): void
    {
        $this->earn(7121, '100', '2026-01-01');
        try {
            $this->conversion->convert(self::CUSTOMER_A, 'db@test.local', 101, 'req-over', $this->at('2026-02-01'));
            $this->fail('Expected refusal');
        } catch (LocalizedException $e) {
            $this->assertSame(100, $this->pointsBalance());
            $this->assertSame('0.0000', $this->walletBalance(), 'Wallet not credited');
            $this->assertLedgersReconcile(self::CUSTOMER_A);
        }
    }

    public function testBelowMinimumIsRefused(): void
    {
        $this->earn(7131, '100', '2026-01-01');
        $this->expectException(LocalizedException::class);
        $this->conversion->convert(self::CUSTOMER_A, 'db@test.local', 4, 'req-min', $this->at('2026-02-01'));
    }

    public function testPointsPastExpiryAreNotSpendableEvenBeforeTheCronRuns(): void
    {
        $this->earn(7141, '100', '2025-01-01'); // expires 2026-01-01
        $fresh = $this->earn(7142, '100', '2026-03-01');
        try {
            $this->conversion->convert(self::CUSTOMER_A, 'db@test.local', 150, 'req-stale', $this->at('2026-03-15'));
            $this->fail('Expected refusal');
        } catch (LocalizedException $e) {
            $this->assertStringContainsString('100', (string)$e->getMessage());
            $this->assertSame(200, $this->pointsBalance());
        }
        $this->conversion->convert(self::CUSTOMER_A, 'db@test.local', 100, 'req-fresh', $this->at('2026-03-15'));
        $this->assertSame(0, $this->lotRemaining($fresh));
        $this->assertLedgersReconcile(self::CUSTOMER_A);
    }

    // AC-05 -----------------------------------------------------------------------------------------------------

    public function testExpiryRunTwiceExpiresPointsOnce(): void
    {
        $lotA = $this->earn(7201, '100', '2025-01-01'); // expires 2026-01-01
        $lotB = $this->earn(7202, '100', '2025-06-01'); // expires 2026-06-01
        $this->conversion->convert(self::CUSTOMER_A, 'db@test.local', 30, 'req-exp', $this->at('2025-02-01'));
        $this->assertSame(70, $this->lotRemaining($lotA));

        $first = $this->expiry->run($this->at('2026-01-02'));
        $second = $this->expiry->run($this->at('2026-01-02'));

        $this->assertSame(1, $first->lotsProcessed);
        $this->assertSame(70, $first->pointsExpired, 'Only what was left of lot A expires');
        $this->assertSame(0, $second->lotsProcessed);
        $this->assertSame(0, $second->pointsExpired);
        $this->assertSame(100, $this->pointsBalance(), '200 earned - 30 converted - 70 expired');
        $this->assertSame(1, (int)$this->connection->fetchOne(
            'SELECT COUNT(*) FROM ' . $this->table('codilar_loyalty_points_ledger')
            . " WHERE entry_type = 'EXPIRY' AND lot_id = ?",
            [$lotA]
        ));
        $this->assertSame(100, $this->lotRemaining($lotB), 'Lot B has not reached its expiry');
        $this->assertLedgersReconcile(self::CUSTOMER_A);
    }

    public function testFullyUsedLotIsClosedWithoutALedgerEntry(): void
    {
        $lot = $this->earn(7211, '50', '2025-01-01');
        $this->conversion->convert(self::CUSTOMER_A, 'db@test.local', 50, 'req-all', $this->at('2025-02-01'));

        $report = $this->expiry->run($this->at('2026-01-02'));

        $this->assertSame(1, $report->lotsProcessed);
        $this->assertSame(0, $report->pointsExpired);
        $this->assertSame(0, (int)$this->connection->fetchOne(
            'SELECT COUNT(*) FROM ' . $this->table('codilar_loyalty_points_ledger')
            . " WHERE entry_type = 'EXPIRY' AND lot_id = ?",
            [$lot]
        ));
        $this->assertLedgersReconcile(self::CUSTOMER_A);
    }

    public function testExpiryHandlesSeveralCustomersAndDryRunChangesNothing(): void
    {
        $this->earn(7221, '100', '2025-01-01', self::CUSTOMER_A);
        $this->earn(7222, '40', '2025-01-01', self::CUSTOMER_B);

        $preview = $this->expiry->preview($this->at('2026-01-02'));
        $this->assertSame(['lots' => 2, 'points' => 140], $preview);
        $this->assertSame(100, $this->pointsBalance(self::CUSTOMER_A));

        $report = $this->expiry->run($this->at('2026-01-02'));
        $this->assertSame(2, $report->lotsProcessed);
        $this->assertSame(140, $report->pointsExpired);
        $this->assertSame(0, $this->pointsBalance(self::CUSTOMER_A));
        $this->assertSame(0, $this->pointsBalance(self::CUSTOMER_B));
        $this->assertLedgersReconcile(self::CUSTOMER_A);
        $this->assertLedgersReconcile(self::CUSTOMER_B);
    }

    public function testNextExpiryReportsEarliestDateAndItsPoints(): void
    {
        $this->earn(7231, '100', '2026-02-01');
        $this->earn(7232, '30', '2026-02-01');
        $this->earn(7233, '500', '2026-05-01');

        $next = $this->lots->nextExpiry(self::CUSTOMER_A, '2026-03-01 00:00:00');
        $this->assertSame(['expires_at' => '2027-02-01 10:00:00', 'points' => 130], $next);
        $this->assertNull($this->lots->nextExpiry(self::CUSTOMER_B, '2026-03-01 00:00:00'));
    }
}

<?php
declare(strict_types=1);

namespace Codilar\LoyaltyWallet\Test\Db;

use Codilar\LoyaltyWallet\Api\Data\WalletLedgerEntryInterface as W;
use Codilar\LoyaltyWallet\Api\PointsManagementInterface;
use Codilar\LoyaltyWallet\Model\Order\ReversalProcessor;
use Codilar\LoyaltyWallet\Model\Points\ReversalService;
use Codilar\LoyaltyWallet\Model\Sweeper\Sweeper;
use Codilar\LoyaltyWallet\Model\Wallet\WalletMovement;
use Codilar\LoyaltyWallet\Model\Wallet\WalletService;
use Magento\Sales\Model\Order;

class ReversalDbTest extends DbTestCase
{
    private const ORDER_ID = 990501;
    private PointsManagementInterface $points;
    private WalletService $wallet;
    private ReversalService $reversal;
    private int $lotId;

    protected function setUp(): void
    {
        parent::setUp();
        // Fixture rows in sales_order / sales_creditmemo need not point at real customers or orders.
        $this->connection->query('SET FOREIGN_KEY_CHECKS = 0');
        $this->points = $this->om->get(PointsManagementInterface::class);
        $this->wallet = $this->om->get(WalletService::class);
        $this->reversal = $this->om->get(ReversalService::class);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->connection->query('SET FOREIGN_KEY_CHECKS = 1');
    }

    /** Earns $points (rate 1 point per ₹1) on order ORDER_ID + $n. */
    private function earn(int $points, int $n = 0): int
    {
        $this->points->accrue(
            self::CUSTOMER_A,
            'rv@example.com',
            self::ORDER_ID + $n,
            'RV-' . $n,
            (string)$points
        );
        return (int)$this->connection->fetchOne(
            'SELECT lot_id FROM ' . $this->table('codilar_loyalty_points_lot') . ' WHERE order_id = ?',
            [self::ORDER_ID + $n]
        );
    }

    private function reverse(?int $target, int $n = 0): \Codilar\LoyaltyWallet\Model\Points\ReversalResult
    {
        return $this->reversal->reverseTo(self::CUSTOMER_A, self::ORDER_ID + $n, 'RV-' . $n, 'rv@example.com', $target);
    }

    private function pointsBalance(): int
    {
        return $this->points->getBalance(self::CUSTOMER_A);
    }

    private function walletBalance(): string
    {
        return (string)$this->connection->fetchOne(
            'SELECT balance FROM ' . $this->table('codilar_wallet_balance') . ' WHERE customer_id = ?',
            [self::CUSTOMER_A]
        );
    }

    private function spend(string $amount, string $key): void
    {
        $this->wallet->debit(new WalletMovement(
            self::CUSTOMER_A,
            'rv@example.com',
            W::TYPE_DEBIT_MANUAL,
            $amount,
            'spent',
            $key,
            null,
            null,
            null,
            'tester'
        ));
    }

    public function testUnspentPointsAreSimplyRemoved(): void
    {
        $this->earn(100);
        $result = $this->reverse(null);
        $this->assertSame([100, 0, '0.0000', '0.0000'], [
            $result->pointsReversed, $result->pointsClawed, $result->clawedBack, $result->writtenOff,
        ]);
        $this->assertSame(0, $this->pointsBalance());
        $this->assertLedgersReconcile(self::CUSTOMER_A);
    }

    public function testCallingAgainDoesNothing(): void
    {
        $this->earn(100);
        $this->reverse(null);
        $again = $this->reverse(null);
        $this->assertSame(0, $again->pointsReversed);
        $this->assertSame(
            '1',
            (string)$this->connection->fetchOne(
                'SELECT COUNT(*) FROM ' . $this->table('codilar_loyalty_points_ledger')
                . " WHERE customer_id = ? AND entry_type = 'REVERSAL'",
                [self::CUSTOMER_A]
            )
        );
    }

    /** AC-04: cancel after the points were converted and the wallet money spent. */
    public function testCancellationAfterConvertAndSpendLeavesACleanLedgerAndNoNegativeBalance(): void
    {
        $this->earn(100);
        $convert = $this->points->convertToWallet(self::CUSTOMER_A, 'rv@example.com', 100, 'rv-key-1');
        $this->assertSame('10.0000', $convert->walletAmount);
        $this->spend('8', 'rv:spend:1');

        $result = $this->reverse(null);
        $this->assertSame([0, 100, '2.0000', '8.0000'], [
            $result->pointsReversed, $result->pointsClawed, $result->clawedBack, $result->writtenOff,
        ]);
        $this->assertSame('0.0000', $this->walletBalance());
        $this->assertSame(0, $this->pointsBalance());

        $types = $this->connection->fetchCol(
            'SELECT entry_type FROM ' . $this->table('codilar_wallet_ledger') . ' WHERE customer_id = ? ORDER BY entry_id',
            [self::CUSTOMER_A]
        );
        $this->assertSame(['CREDIT_CONVERSION', 'DEBIT_MANUAL', 'CLAWBACK', 'WRITE_OFF'], $types);
        $memo = $this->connection->fetchOne(
            'SELECT memo_amount FROM ' . $this->table('codilar_wallet_ledger') . " WHERE entry_type = 'WRITE_OFF'"
        );
        $this->assertSame('8.0000', $memo);
        $this->assertLedgersReconcile(self::CUSTOMER_A);
        $this->assertSame([], $this->om->get(\Codilar\LoyaltyWallet\Model\Reconciliation\Reconciler::class)->run(self::CUSTOMER_A));
    }

    public function testPartlyConvertedPointsAreTakenFromTheLotFirstThenFromTheWallet(): void
    {
        $this->earn(100);
        $this->points->convertToWallet(self::CUSTOMER_A, 'rv@example.com', 40, 'rv-key-2'); // ₹4.00, 60 points left

        $first = $this->reverse(50);   // 50 of the 60 unspent points
        $this->assertSame([50, 0], [$first->pointsReversed, $first->pointsClawed]);
        $this->assertSame('4.0000', $this->walletBalance());

        $second = $this->reverse(null); // the other 50: 10 unspent, 40 converted
        $this->assertSame([10, 40, '4.0000', '0.0000'], [
            $second->pointsReversed, $second->pointsClawed, $second->clawedBack, $second->writtenOff,
        ]);
        $this->assertSame('0.0000', $this->walletBalance());
        $this->assertSame(0, $this->pointsBalance());
        $this->assertLedgersReconcile(self::CUSTOMER_A);
    }

    public function testSplitClawbacksAddUpToExactlyTheConvertedValue(): void
    {
        $this->earn(100);
        $this->points->convertToWallet(self::CUSTOMER_A, 'rv@example.com', 100, 'rv-key-3'); // ₹10.00
        $this->reverse(33);
        $this->reverse(66);
        $this->reverse(null);
        $sum = $this->connection->fetchOne(
            'SELECT COALESCE(SUM(-amount), 0) FROM ' . $this->table('codilar_wallet_ledger')
            . " WHERE customer_id = ? AND entry_type = 'CLAWBACK'",
            [self::CUSTOMER_A]
        );
        $this->assertSame('10.0000', bcadd((string)$sum, '0', 4));
        $this->assertSame('0.0000', $this->walletBalance());
    }

    public function testExpiredPointsNeedNoReversalButConvertedOnesAreStillClawedBack(): void
    {
        $this->earn(100);
        $this->points->convertToWallet(self::CUSTOMER_A, 'rv@example.com', 30, 'rv-key-4'); // ₹3.00, 70 left
        $this->connection->update(
            $this->table('codilar_loyalty_points_lot'),
            ['expires_at' => '2020-01-01 00:00:00'],
            ['order_id = ?' => self::ORDER_ID]
        );
        $this->om->get(\Codilar\LoyaltyWallet\Model\Points\ExpiryProcessor::class)->run();
        $this->assertSame(0, $this->pointsBalance());

        $result = $this->reverse(null);
        $this->assertSame([0, 30, '3.0000'], [$result->pointsReversed, $result->pointsClawed, $result->clawedBack]);
        $this->assertSame('0.0000', $this->walletBalance());
        $this->assertLedgersReconcile(self::CUSTOMER_A);
    }

    public function testAnOrderThatEarnedNothingOrBelongsToSomeoneElseIsLeftAlone(): void
    {
        $this->assertSame(0, $this->reverse(null, 5)->pointsReversed);
        $this->earn(50);
        $other = $this->reversal->reverseTo(self::CUSTOMER_B, self::ORDER_ID, 'RV-0', 'x@example.com', null);
        $this->assertSame(0, $other->pointsReversed);
        $this->assertSame(50, $this->pointsBalance());
    }

    // ---- processor: cancel and refund ------------------------------------------------------------------------

    private function order(array $extra = []): Order
    {
        return $this->om->create(Order::class)->setData($extra + [
            'entity_id' => self::ORDER_ID, 'increment_id' => 'RV-0', 'customer_id' => self::CUSTOMER_A,
            'customer_email' => 'rv@example.com', 'store_id' => 1, 'state' => Order::STATE_CANCELED,
            'base_subtotal' => 100.0, 'base_discount_amount' => 0.0,
        ]);
    }

    public function testCancelRestoresTheUninvoicedWalletAmountOnceAndReversesPoints(): void
    {
        $this->earn(100);
        $this->wallet->credit(new WalletMovement(
            self::CUSTOMER_A, 'rv@example.com', W::TYPE_CREDIT_MANUAL, '100', 'seed', 'rv:seed', null, null, null, 't'
        ));
        $this->wallet->debit(new WalletMovement(
            self::CUSTOMER_A, 'rv@example.com', W::TYPE_DEBIT_REDEMPTION, '30', 'order', 'rv:redeem', null, 'RV-0'
        ));
        $order = $this->order(['codilar_wallet_amount' => '30.0000', 'codilar_wallet_invoiced' => '0.0000']);
        $processor = $this->om->get(ReversalProcessor::class);

        $processor->process($order);
        $processor->process($order);

        $this->assertSame('100.0000', $this->walletBalance());
        $this->assertSame(0, $this->pointsBalance());
        $this->assertSame(
            '1',
            (string)$this->connection->fetchOne(
                'SELECT COUNT(*) FROM ' . $this->table('codilar_wallet_ledger') . " WHERE entry_type = 'CREDIT_CANCELLATION'"
            )
        );
        $this->assertLedgersReconcile(self::CUSTOMER_A);
    }

    public function testCancelOnlyRestoresTheWalletAmountThatWasNotInvoiced(): void
    {
        $this->wallet->credit(new WalletMovement(
            self::CUSTOMER_A, 'rv@example.com', W::TYPE_CREDIT_MANUAL, '100', 'seed', 'rv:seed2', null, null, null, 't'
        ));
        $this->wallet->debit(new WalletMovement(
            self::CUSTOMER_A, 'rv@example.com', W::TYPE_DEBIT_REDEMPTION, '30', 'order', 'rv:redeem2', null, 'RV-0'
        ));
        $order = $this->order(['codilar_wallet_amount' => '30.0000', 'codilar_wallet_invoiced' => '20.0000']);
        $this->om->get(ReversalProcessor::class)->process($order);
        $this->assertSame('80.0000', $this->walletBalance()); // 70 + 10 not yet invoiced
    }

    public function testRefundReversesPointsInProportionAndFullRefundTakesTheRest(): void
    {
        $this->earn(100);
        $order = $this->order(['state' => Order::STATE_PROCESSING, 'base_subtotal' => 100.0]);
        $processor = $this->om->get(ReversalProcessor::class);
        $memo = function (string $amount, int $n): void {
            $this->connection->insert($this->table('sales_creditmemo'), [
                'order_id' => self::ORDER_ID, 'state' => 2, 'base_subtotal' => $amount,
                'base_discount_amount' => 0, 'increment_id' => 'RVM-' . $n,
            ]);
        };

        $processor->process($order);
        $this->assertSame(100, $this->pointsBalance(), 'no refund, nothing reversed');

        $memo('25', 1);
        $processor->process($order);
        $this->assertSame(75, $this->pointsBalance());

        $processor->process($order);
        $this->assertSame(75, $this->pointsBalance(), 'same memo again changes nothing');

        $memo('75', 2);
        $processor->process($order);
        $this->assertSame(0, $this->pointsBalance());
        $this->assertLedgersReconcile(self::CUSTOMER_A);
    }

    public function testRefundWithADiscountUsesTheSubtotalAfterDiscount(): void
    {
        $this->earn(80); // subtotal 100, discount 20 -> 80 eligible
        $order = $this->order(['state' => Order::STATE_PROCESSING, 'base_subtotal' => 100.0, 'base_discount_amount' => -20.0]);
        $this->connection->insert($this->table('sales_creditmemo'), [
            'order_id' => self::ORDER_ID, 'state' => 2, 'base_subtotal' => '50', 'base_discount_amount' => '-10',
            'increment_id' => 'RVM-D1',
        ]);
        $this->om->get(ReversalProcessor::class)->process($order);
        $this->assertSame(40, $this->pointsBalance()); // 40 of 80 eligible refunded -> half
    }

    // ---- sweeper catch-up ------------------------------------------------------------------------------------

    public function testSweeperFinishesReversalsThatTheObserverMissed(): void
    {
        $this->earn(100);
        $this->connection->insert($this->table('sales_order'), [
            'entity_id' => self::ORDER_ID, 'increment_id' => 'RV-0', 'customer_id' => self::CUSTOMER_A,
            'customer_email' => 'rv@example.com', 'customer_is_guest' => 0, 'state' => 'canceled',
            'base_subtotal' => 100, 'base_discount_amount' => 0, 'store_id' => 1,
            'created_at' => gmdate('Y-m-d H:i:s'), 'updated_at' => gmdate('Y-m-d H:i:s'),
        ]);
        $report = $this->om->get(Sweeper::class)->run();
        $this->assertSame(1, $report['reversed']);
        $this->assertSame(0, $this->pointsBalance());
        $this->assertSame(0, $this->om->get(Sweeper::class)->run()['reversed'], 'nothing left to do the second time');
    }
}

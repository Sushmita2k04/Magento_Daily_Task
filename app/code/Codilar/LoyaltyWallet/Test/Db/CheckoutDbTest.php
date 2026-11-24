<?php
declare(strict_types=1);

namespace Codilar\LoyaltyWallet\Test\Db;

use Codilar\LoyaltyWallet\Api\Data\WalletLedgerEntryInterface as W;
use Codilar\LoyaltyWallet\Model\Config;
use Codilar\LoyaltyWallet\Model\Storefront\BalanceReader;
use Codilar\LoyaltyWallet\Model\Sweeper\Sweeper;
use Codilar\LoyaltyWallet\Model\Total\Creditmemo\Wallet as CreditmemoWallet;
use Codilar\LoyaltyWallet\Model\Total\Invoice\Wallet as InvoiceWallet;
use Codilar\LoyaltyWallet\Model\Total\Quote\Wallet as QuoteWallet;
use Codilar\LoyaltyWallet\Model\Wallet\WalletMovement;
use Codilar\LoyaltyWallet\Model\Wallet\WalletService;
use Codilar\LoyaltyWallet\Observer\AccruePoints;
use Codilar\LoyaltyWallet\Observer\RedeemWallet;
use Codilar\LoyaltyWallet\Observer\ReleaseWallet;
use Magento\Framework\Event;
use Magento\Framework\Event\Observer;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Api\Data\ShippingAssignmentInterface;
use Magento\Quote\Api\Data\ShippingInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address;
use Magento\Quote\Model\Quote\Address\Total;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Creditmemo;
use Magento\Sales\Model\Order\Invoice;

class CheckoutDbTest extends DbTestCase
{
    private WalletService $wallet;

    protected function setUp(): void
    {
        parent::setUp();
        $this->wallet = $this->om->get(WalletService::class);
    }

    private function fund(string $amount, int $customer = self::CUSTOMER_A): void
    {
        $this->wallet->credit(new WalletMovement(
            $customer,
            'co@example.com',
            W::TYPE_CREDIT_MANUAL,
            $amount,
            'seed',
            'co:seed:' . $customer . ':' . $amount . microtime(true),
            null,
            null,
            null,
            'tester'
        ));
    }

    private function config(string $percent = '50', bool $refundToWallet = true): Config
    {
        $config = $this->createStub(Config::class);
        $config->method('isEnabled')->willReturn(true);
        $config->method('getMaxRedemptionPercent')->willReturn($percent);
        $config->method('isRefundToWallet')->willReturn($refundToWallet);
        return $config;
    }

    private function quote(array $data): Quote
    {
        return $this->om->create(Quote::class)->setData($data + [
            'customer_id' => self::CUSTOMER_A,
            'store_id' => 1,
            'quote_currency_code' => 'INR',
            'base_currency_code' => 'INR',
            'customer_email' => 'co@example.com',
        ]);
    }

    /**
     * Runs the quote total collector with a grand total of 100 and returns [quote amount, grand total after].
     */
    private function collectQuote(Quote $quote, string $percent = '50', float $grand = 100.0, bool $items = true): array
    {
        $assignment = $this->createStub(ShippingAssignmentInterface::class);
        $shipping = $this->createStub(ShippingInterface::class);
        $shipping->method('getAddress')->willReturn($this->om->create(Address::class));
        $assignment->method('getShipping')->willReturn($shipping);
        $assignment->method('getItems')->willReturn($items ? [new \stdClass()] : []);
        $total = $this->om->create(Total::class)->setGrandTotal($grand)->setBaseGrandTotal($grand);
        (new QuoteWallet($this->config($percent), $this->om->get(BalanceReader::class)))
            ->collect($quote, $assignment, $total);
        return [(string)$quote->getData('codilar_wallet_amount'), (float)$total->getGrandTotal()];
    }

    private function balance(int $customer = self::CUSTOMER_A): string
    {
        return (string)$this->connection->fetchOne(
            'SELECT balance FROM ' . $this->table('codilar_wallet_balance') . ' WHERE customer_id = ?',
            [$customer]
        );
    }

    // ---- quote totals (FR-08, BR-04) -------------------------------------------------------------------------

    public function testUseAllIsCappedByTheMaximumRedemptionShare(): void
    {
        $this->fund('80');
        $this->assertSame(['50.0000', 50.0], $this->collectQuote($this->quote(['codilar_wallet_use_all' => 1])));
    }

    public function testUseAllIsLimitedByTheBalanceWhenThatIsLower(): void
    {
        $this->fund('30');
        $this->assertSame(['30.0000', 70.0], $this->collectQuote($this->quote(['codilar_wallet_use_all' => 1])));
    }

    public function testAPartialAmountIsHonouredAndNeverExceedsTheCapOrTheOrder(): void
    {
        $this->fund('500');
        $this->assertSame(
            ['20.0000', 80.0],
            $this->collectQuote($this->quote(['codilar_wallet_requested' => '20']))
        );
        $this->assertSame(
            ['50.0000', 50.0],
            $this->collectQuote($this->quote(['codilar_wallet_requested' => '400']))
        );
        $this->assertSame(
            ['100.0000', 0.0],
            $this->collectQuote($this->quote(['codilar_wallet_use_all' => 1]), '100')
        );
        $this->assertSame(
            ['0.0000', 100.0],
            $this->collectQuote($this->quote(['codilar_wallet_use_all' => 1]), '0')
        );
    }

    public function testNothingIsAppliedForGuestsNotRequestedOtherCurrenciesOrEmptyWallets(): void
    {
        $this->fund('80');
        $zero = ['0.0000', 100.0];
        $this->assertSame($zero, $this->collectQuote($this->quote([])), 'not requested');
        $this->assertSame($zero, $this->collectQuote($this->quote([
            'codilar_wallet_use_all' => 1, 'customer_id' => null,
        ])), 'guest');
        $this->assertSame($zero, $this->collectQuote($this->quote([
            'codilar_wallet_use_all' => 1, 'quote_currency_code' => 'USD',
        ])), 'not INR');
        $this->assertSame($zero, $this->collectQuote($this->quote([
            'codilar_wallet_use_all' => 1, 'customer_id' => self::CUSTOMER_B,
        ])), 'no balance');
    }

    public function testTheAddressWithoutItemsIsLeftAlone(): void
    {
        $this->fund('80');
        $quote = $this->quote(['codilar_wallet_use_all' => 1, 'codilar_wallet_amount' => '12.0000']);
        $this->assertSame(['12.0000', 100.0], $this->collectQuote($quote, '50', 100.0, false));
    }

    // ---- redeem / release (NFR-01, AC-03) --------------------------------------------------------------------

    private function placement(string $amount, string $incrementId): array
    {
        $order = $this->om->create(Order::class)->setIncrementId($incrementId);
        $quote = $this->quote(['codilar_wallet_amount' => $amount]);
        return [$order, $quote, new Observer(['event' => new Event(['order' => $order, 'quote' => $quote])])];
    }

    public function testRedeemingDebitsTheWalletOnceAndStampsTheOrder(): void
    {
        $this->fund('100');
        [$order, , $observer] = $this->placement('40.0000', 'CO-R1');
        $this->om->get(RedeemWallet::class)->execute($observer);

        $this->assertSame('60.0000', $this->balance());
        $this->assertSame('40.0000', $order->getData('codilar_wallet_amount'));
        $row = $this->connection->fetchRow(
            'SELECT entry_type, amount, order_increment_id FROM ' . $this->table('codilar_wallet_ledger')
            . ' WHERE customer_id = ? AND entry_type = ?',
            [self::CUSTOMER_A, W::TYPE_DEBIT_REDEMPTION]
        );
        $this->assertSame(['entry_type' => 'DEBIT_REDEMPTION', 'amount' => '-40.0000', 'order_increment_id' => 'CO-R1'], $row);
        $this->assertLedgersReconcile(self::CUSTOMER_A);
    }

    /** AC-03: two orders racing for the same balance, exactly one succeeds. */
    public function testSecondRedemptionOfTheSameBalanceFailsAndNothingIsDoubleSpent(): void
    {
        $this->fund('100');
        [, , $first] = $this->placement('80.0000', 'CO-R2');
        [$secondOrder, , $second] = $this->placement('80.0000', 'CO-R3');
        $redeem = $this->om->get(RedeemWallet::class);

        $redeem->execute($first);
        try {
            $redeem->execute($second);
            $this->fail('The second redemption should have been refused');
        } catch (LocalizedException $e) {
            $this->assertStringContainsString('wallet balance changed', $e->getMessage());
        }
        $this->assertSame('20.0000', $this->balance());
        $this->assertNull($secondOrder->getData('codilar_wallet_amount'));
        $this->assertLedgersReconcile(self::CUSTOMER_A);
    }

    public function testNothingHappensWhenTheQuoteHasNoWallet(): void
    {
        [$order, , $observer] = $this->placement('0.0000', 'CO-R4');
        $this->om->get(RedeemWallet::class)->execute($observer);
        $this->assertNull($order->getData('codilar_wallet_redeem_key'));
        $this->assertSame('', $this->balance());
    }

    public function testAFailedOrderPutsTheMoneyBack(): void
    {
        $this->fund('100');
        [$order, , $observer] = $this->placement('40.0000', 'CO-R5');
        $this->om->get(RedeemWallet::class)->execute($observer);
        $this->om->get(ReleaseWallet::class)->execute($observer);

        $this->assertSame('100.0000', $this->balance());
        $types = $this->connection->fetchCol(
            'SELECT entry_type FROM ' . $this->table('codilar_wallet_ledger') . ' WHERE customer_id = ? ORDER BY entry_id',
            [self::CUSTOMER_A]
        );
        $this->assertSame(['CREDIT_MANUAL', 'DEBIT_REDEMPTION', 'REDEMPTION_RELEASE'], $types);

        $this->om->get(ReleaseWallet::class)->execute($observer); // repeating changes nothing
        $this->assertSame('100.0000', $this->balance());
        $this->assertLedgersReconcile(self::CUSTOMER_A);
    }

    public function testASavedOrderIsNotReleased(): void
    {
        $this->fund('100');
        [, , $observer] = $this->placement('40.0000', 'CO-R6');
        $this->om->get(RedeemWallet::class)->execute($observer);
        $this->connection->insert($this->table('sales_order'), ['increment_id' => 'CO-R6', 'state' => 'new']);

        $this->om->get(ReleaseWallet::class)->execute($observer);
        $this->assertSame('60.0000', $this->balance());
    }

    // ---- sweeper ---------------------------------------------------------------------------------------------

    public function testSweeperReleasesOnlyOldDebitsWhoseOrderWasNeverSaved(): void
    {
        $this->fund('300');
        foreach (['CO-S1', 'CO-S2', 'CO-S3'] as $inc) {
            [, , $observer] = $this->placement('50.0000', $inc);
            $this->om->get(RedeemWallet::class)->execute($observer);
        }
        $this->connection->insert($this->table('sales_order'), ['increment_id' => 'CO-S2', 'state' => 'new']);
        // age two of the debits (raw SQL: the immutability guard is an application rule)
        $this->connection->update(
            $this->table('codilar_wallet_ledger'),
            ['created_at' => '2020-01-01 00:00:00'],
            ['order_increment_id IN (?)' => ['CO-S1', 'CO-S2']]
        );

        $report = $this->om->get(Sweeper::class)->run(60, 1);
        $this->assertSame(1, $report['released']);       // S1: old and no order. S2 has an order, S3 is recent.
        $this->assertSame('200.0000', $this->balance());  // 300 - 50 (S2) - 50 (S3)
        $this->assertSame(0, $this->om->get(Sweeper::class)->run(60, 1)['released']);
    }

    // ---- accrual wiring (AC-01) ------------------------------------------------------------------------------

    public function testAccrualUsesTheSubtotalAfterDiscountsAndIsIdempotent(): void
    {
        $order = $this->om->create(Order::class)->setData([
            'entity_id' => 990777, 'increment_id' => 'CO-A1', 'customer_id' => self::CUSTOMER_A,
            'customer_email' => 'co@example.com', 'store_id' => 1,
            'base_subtotal' => 250.0, 'base_discount_amount' => -50.0, 'base_shipping_amount' => 40.0,
        ]);
        $observer = new Observer(['event' => new Event(['order' => $order])]);
        $accrue = $this->om->get(AccruePoints::class);
        $accrue->execute($observer);
        $accrue->execute($observer);

        $rows = $this->connection->fetchAll(
            'SELECT entry_type, points FROM ' . $this->table('codilar_loyalty_points_ledger') . ' WHERE customer_id = ?',
            [self::CUSTOMER_A]
        );
        $this->assertSame([['entry_type' => 'ACCRUAL', 'points' => '200']], $rows); // 250 - 50, shipping excluded
        $this->assertLedgersReconcile(self::CUSTOMER_A);
    }

    public function testGuestOrdersEarnNothingAndAccrualErrorsNeverEscape(): void
    {
        $guest = $this->om->create(Order::class)->setData([
            'entity_id' => 990778, 'increment_id' => 'CO-A2', 'customer_id' => null, 'customer_is_guest' => 1,
            'base_subtotal' => 100.0,
        ]);
        $this->om->get(AccruePoints::class)->execute(new Observer(['event' => new Event(['order' => $guest])]));
        $bad = $this->om->create(Order::class)->setData([
            'entity_id' => 990779, 'increment_id' => 'CO-A3', 'customer_id' => self::CUSTOMER_A,
            'customer_email' => 'not-an-email', 'base_subtotal' => 100.0,
        ]);
        $this->om->get(AccruePoints::class)->execute(new Observer(['event' => new Event(['order' => $bad])]));
        $this->assertSame(
            '0',
            (string)$this->connection->fetchOne(
                'SELECT COUNT(*) FROM ' . $this->table('codilar_loyalty_points_ledger') . ' WHERE customer_id = ?',
                [self::CUSTOMER_A]
            )
        );
    }

    // ---- invoice and credit memo (NFR-04) --------------------------------------------------------------------

    private function order(array $data): Order
    {
        return $this->om->create(Order::class)->setData($data + ['store_id' => 1]);
    }

    public function testInvoiceTakesOffTheWalletShareEarliestInvoiceFirst(): void
    {
        $order = $this->order(['codilar_wallet_amount' => '60.0000', 'codilar_wallet_invoiced' => '0.0000']);
        $collector = new InvoiceWallet();

        $first = $this->om->create(Invoice::class)->setOrder($order)->setGrandTotal(40.0)->setBaseGrandTotal(40.0);
        $collector->collect($first);
        $this->assertSame([0.0, '40.0000'], [$first->getGrandTotal(), $first->getData('codilar_wallet_amount')]);

        $order->setData('codilar_wallet_invoiced', '40.0000');
        $second = $this->om->create(Invoice::class)->setOrder($order)->setGrandTotal(100.0)->setBaseGrandTotal(100.0);
        $collector->collect($second);
        $this->assertSame([80.0, '20.0000'], [$second->getGrandTotal(), $second->getData('codilar_wallet_amount')]);

        $order->setData('codilar_wallet_invoiced', '60.0000');
        $third = $this->om->create(Invoice::class)->setOrder($order)->setGrandTotal(30.0)->setBaseGrandTotal(30.0);
        $collector->collect($third);
        $this->assertSame([30.0, '0.0000'], [$third->getGrandTotal(), $third->getData('codilar_wallet_amount')]);
    }

    public function testInvoiceWithoutWalletIsUntouched(): void
    {
        $invoice = $this->om->create(Invoice::class)->setOrder($this->order([]))->setGrandTotal(55.5)->setBaseGrandTotal(55.5);
        (new InvoiceWallet())->collect($invoice);
        $this->assertSame([55.5, '0.0000'], [$invoice->getGrandTotal(), $invoice->getData('codilar_wallet_amount')]);
    }

    private function memo(Order $order, float $gross, bool $toWallet = true): Creditmemo
    {
        $memo = $this->om->create(Creditmemo::class)->setOrder($order)->setGrandTotal($gross)->setBaseGrandTotal($gross);
        (new CreditmemoWallet($this->config('50', $toWallet)))->collect($memo);
        return $memo;
    }

    public function testCreditMemoReturnsTheWalletShareProportionallyAndTheRestToThePaymentMethod(): void
    {
        // Order of 100 gross: 40 paid from the wallet, 60 by card, all invoiced.
        $order = $this->order([
            'codilar_wallet_invoiced' => '40.0000', 'codilar_wallet_refunded' => '0.0000',
            'total_invoiced' => 60.0, 'total_refunded' => 0.0,
        ]);
        $half = $this->memo($order, 50.0);
        $this->assertSame([30.0, '20.0000'], [$half->getGrandTotal(), $half->getData('codilar_wallet_amount')]);

        // the other half: everything still outstanding goes back, nothing is lost to rounding
        $order->setData('codilar_wallet_refunded', '20.0000')->setData('total_refunded', 30.0);
        $rest = $this->memo($order, 50.0);
        $this->assertSame([30.0, '20.0000'], [$rest->getGrandTotal(), $rest->getData('codilar_wallet_amount')]);
    }

    public function testCreditMemoNeverReturnsMoreThanWasPaidFromTheWalletOrMoreThanTheMemo(): void
    {
        $order = $this->order([
            'codilar_wallet_invoiced' => '40.0000', 'codilar_wallet_refunded' => '35.0000',
            'total_invoiced' => 60.0, 'total_refunded' => 52.0,
        ]);
        // A small memo takes its proportional share: 3 of 100 gross is 1.2 from the wallet.
        $small = $this->memo($order, 3.0);
        $this->assertSame([1.8, '1.2000'], [$small->getGrandTotal(), $small->getData('codilar_wallet_amount')]);

        // The memo that completes the refund returns exactly what is left (5), not more.
        $last = $this->memo($order, 13.0);
        $this->assertSame([8.0, '5.0000'], [$last->getGrandTotal(), $last->getData('codilar_wallet_amount')]);

        // Nothing left in the wallet share: the memo goes entirely to the payment method.
        $order->setData('codilar_wallet_refunded', '40.0000');
        $none = $this->memo($order, 2.0);
        $this->assertSame([2.0, '0.0000'], [$none->getGrandTotal(), $none->getData('codilar_wallet_amount')]);
    }

    public function testCreditMemoLeavesEverythingToThePaymentMethodWhenTheSwitchIsOff(): void
    {
        $order = $this->order(['codilar_wallet_invoiced' => '40.0000', 'total_invoiced' => 60.0]);
        $memo = $this->memo($order, 50.0, false);
        $this->assertSame([50.0, '0.0000'], [$memo->getGrandTotal(), $memo->getData('codilar_wallet_amount')]);
    }
}

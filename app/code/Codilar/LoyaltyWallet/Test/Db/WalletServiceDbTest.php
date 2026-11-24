<?php
declare(strict_types=1);

namespace Codilar\LoyaltyWallet\Test\Db;

use Codilar\LoyaltyWallet\Api\Data\WalletLedgerEntryInterface as W;
use Codilar\LoyaltyWallet\Model\Reconciliation\Reconciler;
use Codilar\LoyaltyWallet\Model\Wallet\WalletMovement;
use Codilar\LoyaltyWallet\Model\Wallet\WalletService;
use Magento\Framework\Exception\LocalizedException;

class WalletServiceDbTest extends DbTestCase
{
    private WalletService $wallet;

    protected function setUp(): void
    {
        parent::setUp();
        $this->wallet = $this->om->get(WalletService::class);
    }

    private function move(string $type, string $amount, string $key, int $customer = self::CUSTOMER_A): WalletMovement
    {
        $manual = in_array($type, [W::TYPE_CREDIT_MANUAL, W::TYPE_DEBIT_MANUAL], true);
        return new WalletMovement(
            $customer,
            'wallet-test@example.com',
            $type,
            $amount,
            'test movement',
            $key,
            700001,
            '000000701',
            null,
            $manual ? 'tester' : null
        );
    }

    private function balance(int $customer = self::CUSTOMER_A): string
    {
        return (string)$this->connection->fetchOne(
            'SELECT balance FROM ' . $this->table('codilar_wallet_balance') . ' WHERE customer_id = ?',
            [$customer]
        );
    }

    private function ledgerCount(int $customer = self::CUSTOMER_A): int
    {
        return (int)$this->connection->fetchOne(
            'SELECT COUNT(*) FROM ' . $this->table('codilar_wallet_ledger') . ' WHERE customer_id = ?',
            [$customer]
        );
    }

    public function testCreditAddsToBalanceAndWritesOneLedgerRow(): void
    {
        $entry = $this->wallet->credit($this->move(W::TYPE_CREDIT_REFUND, '12.5', 'refund:1'));
        $this->assertSame('12.5000', $entry->getAmount());
        $this->assertSame('12.5000', $entry->getBalanceAfter());
        $this->assertSame('12.5000', $this->balance());
        $this->assertSame(1, $this->ledgerCount());
        $this->assertLedgersReconcile(self::CUSTOMER_A);
    }

    public function testRepeatedKeyIsReplayedNotPostedTwice(): void
    {
        $first = $this->wallet->credit($this->move(W::TYPE_CREDIT_CANCELLATION, '10', 'cancel:1'));
        $second = $this->wallet->credit($this->move(W::TYPE_CREDIT_CANCELLATION, '10', 'cancel:1'));
        $this->assertSame($first->getEntryId(), $second->getEntryId());
        $this->assertSame('10.0000', $this->balance());
        $this->assertSame(1, $this->ledgerCount());
    }

    public function testKeyUsedForAnotherCustomerIsRefused(): void
    {
        $this->wallet->credit($this->move(W::TYPE_CREDIT_REFUND, '5', 'shared-key'));
        $this->expectException(LocalizedException::class);
        $this->wallet->credit($this->move(W::TYPE_CREDIT_REFUND, '5', 'shared-key', self::CUSTOMER_B));
    }

    public function testDebitCannotTakeMoreThanTheBalance(): void
    {
        $this->wallet->credit($this->move(W::TYPE_CREDIT_MANUAL, '5', 'm:1'));
        try {
            $this->wallet->debit($this->move(W::TYPE_DEBIT_MANUAL, '5.0001', 'm:2'));
            $this->fail('Expected refusal');
        } catch (LocalizedException $e) {
            $this->assertSame('5.0000', $this->balance());
            $this->assertSame(1, $this->ledgerCount());
        }
        $this->wallet->debit($this->move(W::TYPE_DEBIT_MANUAL, '5', 'm:3'));
        $this->assertSame('0.0000', $this->balance());
        $this->assertLedgersReconcile(self::CUSTOMER_A);
    }

    public function testExactDecimalArithmeticHasNoFloatDrift(): void
    {
        foreach (['0.1', '0.2'] as $i => $amount) {
            $this->wallet->credit($this->move(W::TYPE_CREDIT_REFUND, $amount, 'drift:' . $i));
        }
        $this->assertSame('0.3000', $this->balance());
    }

    public function testWrongTypeAndBadAmountsAreRefused(): void
    {
        foreach ([
            [W::TYPE_DEBIT_MANUAL, '5'], [W::TYPE_CREDIT_REFUND, '0'], [W::TYPE_CREDIT_REFUND, '-5'],
            [W::TYPE_CREDIT_REFUND, '1.23456'], [W::TYPE_CREDIT_REFUND, 'abc'],
        ] as $i => [$type, $amount]) {
            try {
                $this->wallet->credit($this->move($type, $amount, 'bad:' . $i));
                $this->fail("Expected refusal for $type $amount");
            } catch (LocalizedException) {
                $this->assertSame(0, $this->ledgerCount());
            }
        }
    }

    public function testManualEntryWithoutOperatorIsRefusedAndRollsBack(): void
    {
        $movement = new WalletMovement(self::CUSTOMER_A, 'a@example.com', W::TYPE_CREDIT_MANUAL, '5', 'why', 'op:1');
        try {
            $this->wallet->credit($movement);
            $this->fail('Expected refusal');
        } catch (\Magento\Framework\Exception\InputException) {
            $this->assertSame(0, $this->ledgerCount());
        }
    }

    public function testClawbackWithEnoughBalanceTakesTheFullAmount(): void
    {
        $this->wallet->credit($this->move(W::TYPE_CREDIT_REFUND, '20', 'c:1'));
        $result = $this->wallet->clawback($this->move(W::TYPE_CLAWBACK, '15', 'claw:1'));
        $this->assertSame('15.0000', $result->clawedBack);
        $this->assertSame('0.0000', $result->writtenOff);
        $this->assertSame('5.0000', $result->walletBalance);
        $this->assertSame(2, $this->ledgerCount());
        $this->assertLedgersReconcile(self::CUSTOMER_A);
    }

    /** AC-04: reverse points that were converted and partly spent. */
    public function testClawbackIsClippedAtZeroAndTheResidualIsWrittenOff(): void
    {
        $this->wallet->credit($this->move(W::TYPE_CREDIT_REFUND, '4', 'c:1'));
        $result = $this->wallet->clawback($this->move(W::TYPE_CLAWBACK, '10', 'claw:2'));
        $this->assertSame('4.0000', $result->clawedBack);
        $this->assertSame('6.0000', $result->writtenOff);
        $this->assertSame('0.0000', $result->walletBalance);
        $this->assertSame('0.0000', $this->balance());

        $rows = $this->connection->fetchAll(
            'SELECT entry_type, amount, memo_amount, balance_after FROM ' . $this->table('codilar_wallet_ledger')
            . ' WHERE customer_id = ? ORDER BY entry_id',
            [self::CUSTOMER_A]
        );
        $this->assertSame(['CREDIT_REFUND', 'CLAWBACK', 'WRITE_OFF'], array_column($rows, 'entry_type'));
        $this->assertSame('-4.0000', $rows[1]['amount']);
        $this->assertSame('0.0000', $rows[2]['amount']);
        $this->assertSame('6.0000', $rows[2]['memo_amount']);
        $this->assertLedgersReconcile(self::CUSTOMER_A);
    }

    public function testClawbackWithEmptyWalletWritesOnlyAWriteOff(): void
    {
        $result = $this->wallet->clawback($this->move(W::TYPE_CLAWBACK, '7.25', 'claw:3'));
        $this->assertSame('0.0000', $result->clawedBack);
        $this->assertSame('7.2500', $result->writtenOff);
        $this->assertSame(1, $this->ledgerCount());
        $this->assertSame('0.0000', $this->balance());
    }

    public function testClawbackReplayChangesNothing(): void
    {
        $this->wallet->credit($this->move(W::TYPE_CREDIT_REFUND, '4', 'c:1'));
        $first = $this->wallet->clawback($this->move(W::TYPE_CLAWBACK, '10', 'claw:4'));
        $this->wallet->credit($this->move(W::TYPE_CREDIT_REFUND, '3', 'c:2'));
        $again = $this->wallet->clawback($this->move(W::TYPE_CLAWBACK, '10', 'claw:4'));
        $this->assertTrue($again->replayed);
        $this->assertSame($first->clawedBack, $again->clawedBack);
        $this->assertSame($first->writtenOff, $again->writtenOff);
        $this->assertSame('3.0000', $this->balance());
        $this->assertSame(4, $this->ledgerCount());
    }

    public function testReconcilerIsCleanAfterNormalActivity(): void
    {
        $this->wallet->credit($this->move(W::TYPE_CREDIT_REFUND, '9', 'r:1'));
        $this->wallet->debit($this->move(W::TYPE_DEBIT_MANUAL, '2', 'r:2'));
        $this->wallet->clawback($this->move(W::TYPE_CLAWBACK, '20', 'r:3'));
        $this->assertSame([], $this->om->get(Reconciler::class)->run(self::CUSTOMER_A));
    }

    public function testReconcilerDetectsTamperedBalanceAndTamperedLedgerRow(): void
    {
        $this->wallet->credit($this->move(W::TYPE_CREDIT_REFUND, '9', 't:1'));
        $this->wallet->credit($this->move(W::TYPE_CREDIT_REFUND, '1', 't:2'));
        $reconciler = $this->om->get(Reconciler::class);

        $this->connection->query(
            'UPDATE ' . $this->table('codilar_wallet_balance') . ' SET balance = 99 WHERE customer_id = ?',
            [self::CUSTOMER_A]
        );
        $checks = array_column($reconciler->run(self::CUSTOMER_A), 'check');
        $this->assertContains('wallet balance differs from wallet ledger', $checks);

        $this->connection->query(
            'UPDATE ' . $this->table('codilar_wallet_balance') . ' SET balance = 10 WHERE customer_id = ?',
            [self::CUSTOMER_A]
        );
        $this->assertSame([], $reconciler->run(self::CUSTOMER_A));
        $this->connection->query(
            'UPDATE ' . $this->table('codilar_wallet_ledger') . ' SET balance_after = 1 WHERE idempotency_key = ?',
            ['t:1']
        );
        $checks = array_column($reconciler->run(self::CUSTOMER_A), 'check');
        $this->assertContains('wallet entry balance_after is not the running total', $checks);
    }

    /** NFR-01: a second session cannot touch this customer's wallet until the posting transaction ends. */
    public function testSecondSessionIsBlockedWhileAMovementIsInFlight(): void
    {
        $this->wallet->credit($this->move(W::TYPE_CREDIT_REFUND, '5', 'lock:1'));
        $other = $this->secondSession(1);
        try {
            $other->query(
                'SELECT balance FROM ' . $this->table('codilar_wallet_balance')
                . ' WHERE customer_id = ' . self::CUSTOMER_A . ' FOR UPDATE'
            );
            $this->fail('The second session acquired a lock that should be held');
        } catch (\PDOException $e) {
            $this->assertSame(1205, (int)($e->errorInfo[1] ?? 0), 'Expected a lock wait timeout');
        } finally {
            $other->rollBack();
        }
    }
}

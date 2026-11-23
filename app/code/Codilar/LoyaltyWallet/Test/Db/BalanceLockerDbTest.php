<?php
declare(strict_types=1);

namespace Codilar\LoyaltyWallet\Test\Db;

use Codilar\LoyaltyWallet\Model\Locking\BalanceLocker;
use Magento\Framework\Exception\LocalizedException;
use PDOException;

class BalanceLockerDbTest extends DbTestCase
{
    private BalanceLocker $locker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->locker = $this->om->get(BalanceLocker::class);
    }

    private function storedWallet(int $customerId): ?string
    {
        $value = $this->connection->fetchOne(
            'SELECT balance FROM ' . $this->table('codilar_wallet_balance') . ' WHERE customer_id = ?',
            [$customerId]
        );
        return $value === false ? null : (string)$value;
    }

    public function testLockCreatesZeroBalanceRowsAndReturnsExactStrings(): void
    {
        $this->assertSame(['wallet' => '0.0000', 'points' => 0], $this->locker->lock(self::CUSTOMER_A));
        $this->locker->setWalletBalance(self::CUSTOMER_A, '0.1');
        $this->locker->setWalletBalance(self::CUSTOMER_A, bcadd('0.1', '0.2', 4));
        $this->assertSame('0.3000', $this->locker->lock(self::CUSTOMER_A)['wallet']);
    }

    public function testNegativeWalletBalanceIsRefusedAndStoredValueIsUnchanged(): void
    {
        $this->locker->lock(self::CUSTOMER_A);
        $this->locker->setWalletBalance(self::CUSTOMER_A, '5');
        try {
            $this->locker->setWalletBalance(self::CUSTOMER_A, '-0.0001');
            $this->fail('Expected refusal');
        } catch (LocalizedException $e) {
            $this->assertSame('5.0000', $this->storedWallet(self::CUSTOMER_A));
        }
    }

    public function testRawNegativeWriteNeverLeavesANegativeBalanceInTheDatabase(): void
    {
        $this->locker->lock(self::CUSTOMER_A);
        // Bypass the application guard. MySQL here runs non-strict, so the unsigned column clamps the value
        // instead of rejecting it: the stored balance can never be negative, but a bug would be silent.
        // This is why the application guard and reconciliation remain the real protection (see README).
        try {
            $this->connection->query(
                'UPDATE ' . $this->table('codilar_wallet_balance') . ' SET balance = -1 WHERE customer_id = ?',
                [self::CUSTOMER_A]
            );
        } catch (\Throwable $e) {
            // A strict-mode server would reject the statement outright, which is equally acceptable.
        }
        $this->assertGreaterThanOrEqual(0, (float)$this->storedWallet(self::CUSTOMER_A));
    }

    public function testLockOutsideATransactionIsRefused(): void
    {
        while ($this->connection->getTransactionLevel() > 0) {
            $this->connection->rollBack();
        }
        $this->expectException(LocalizedException::class);
        $this->locker->lock(self::CUSTOMER_A);
    }

    public function testSecondSessionIsBlockedOnTheSameCustomerButNotOnAnother(): void
    {
        $this->locker->lock(self::CUSTOMER_A);
        $other = $this->secondSession(1);
        try {
            $table = $this->table('codilar_wallet_balance');

            // Different customer: not blocked.
            $other->query('SELECT balance FROM ' . $table . ' WHERE customer_id = ' . self::CUSTOMER_B . ' FOR UPDATE');

            // Same customer: must wait, then time out (1205) because the first session still holds the lock.
            $started = microtime(true);
            try {
                $other->query('SELECT balance FROM ' . $table . ' WHERE customer_id = ' . self::CUSTOMER_A . ' FOR UPDATE');
                $this->fail('The second session acquired a lock that should be held');
            } catch (PDOException $e) {
                $this->assertSame(1205, (int)($e->errorInfo[1] ?? 0), 'Expected a lock wait timeout');
                $this->assertGreaterThanOrEqual(0.9, microtime(true) - $started);
            }
        } finally {
            $other->rollBack();
        }
    }

    public function testSecondSessionProceedsOnceTheFirstReleasesItsLock(): void
    {
        $this->locker->lock(self::CUSTOMER_A);
        $this->locker->setWalletBalance(self::CUSTOMER_A, '9');
        $this->connection->rollBack(); // releases the lock; the balance row never existed outside the transaction

        $other = $this->secondSession(2);
        try {
            $row = $other->query(
                'SELECT balance FROM ' . $this->table('codilar_wallet_balance')
                . ' WHERE customer_id = ' . self::CUSTOMER_A . ' FOR UPDATE'
            )->fetch();
            $this->assertFalse($row, 'Rolled-back balance must not be visible');
        } finally {
            $other->rollBack();
        }
    }
}

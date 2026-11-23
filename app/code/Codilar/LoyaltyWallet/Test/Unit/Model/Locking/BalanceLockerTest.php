<?php
declare(strict_types=1);

namespace Codilar\LoyaltyWallet\Test\Unit\Model\Locking;

use Codilar\LoyaltyWallet\Model\Locking\BalanceLocker;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\TestCase;

class BalanceLockerTest extends TestCase
{
    private function locker(AdapterInterface $connection): BalanceLocker
    {
        $resource = $this->createMock(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($connection);
        $resource->method('getTableName')->willReturnArgument(0);
        return new BalanceLocker($resource);
    }

    public function testLockOutsideTransactionIsRefused(): void
    {
        $connection = $this->createMock(AdapterInterface::class);
        $connection->method('getTransactionLevel')->willReturn(0);
        $connection->expects($this->never())->method('insertOnDuplicate');
        $this->expectException(LocalizedException::class);
        $this->locker($connection)->lock(1);
    }

    public function testWalletIsLockedBeforePointsWithForUpdate(): void
    {
        $tables = [];
        $forUpdate = [];
        $select = $this->createMock(Select::class);
        $select->method('from')->willReturnCallback(function ($table) use (&$tables, $select) {
            $tables[] = $table;
            return $select;
        });
        $select->method('where')->willReturnSelf();
        $select->method('forUpdate')->willReturnCallback(function ($flag) use (&$forUpdate, $select) {
            $forUpdate[] = $flag;
            return $select;
        });
        $connection = $this->createMock(AdapterInterface::class);
        $connection->method('getTransactionLevel')->willReturn(1);
        $connection->method('select')->willReturn($select);
        $connection->method('fetchOne')->willReturnOnConsecutiveCalls('12.5', '40');

        $result = $this->locker($connection)->lock(7);

        $this->assertSame(['codilar_wallet_balance', 'codilar_loyalty_points_balance'], $tables);
        $this->assertSame([true, true], $forUpdate);
        $this->assertSame(['wallet' => '12.5000', 'points' => 40], $result);
    }

    public function testNegativeWalletBalanceIsRefusedAndNothingIsWritten(): void
    {
        $connection = $this->createMock(AdapterInterface::class);
        $connection->expects($this->never())->method('update');
        $this->expectException(LocalizedException::class);
        $this->locker($connection)->setWalletBalance(1, '-0.0001');
    }

    public function testNonDecimalWalletBalanceIsRefused(): void
    {
        $connection = $this->createMock(AdapterInterface::class);
        $connection->expects($this->never())->method('update');
        $this->expectException(LocalizedException::class);
        $this->locker($connection)->setWalletBalance(1, '1.00001');
    }

    public function testNegativePointsBalanceIsRefused(): void
    {
        $connection = $this->createMock(AdapterInterface::class);
        $connection->expects($this->never())->method('update');
        $this->expectException(LocalizedException::class);
        $this->locker($connection)->setPointsBalance(1, -1);
    }

    public function testValidWalletBalanceIsStoredAsScaleFourString(): void
    {
        $connection = $this->createMock(AdapterInterface::class);
        $connection->expects($this->once())->method('update')
            ->with('codilar_wallet_balance', ['balance' => '3.5000'], ['customer_id = ?' => 9]);
        $this->locker($connection)->setWalletBalance(9, '3.5');
    }
}

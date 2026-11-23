<?php
declare(strict_types=1);

namespace Codilar\LoyaltyWallet\Test\Unit\Model;

use Codilar\LoyaltyWallet\Model\ResourceModel\WalletLedger as ResourceModel;
use Codilar\LoyaltyWallet\Model\ResourceModel\WalletLedger\CollectionFactory;
use Codilar\LoyaltyWallet\Model\Validator\WalletLedgerEntryValidator;
use Codilar\LoyaltyWallet\Model\WalletLedger;
use Codilar\LoyaltyWallet\Model\WalletLedgerRepository;
use Magento\Framework\Exception\InputException;
use PHPUnit\Framework\TestCase;

class WalletLedgerRepositoryTest extends TestCase
{
    public function testValidatesBeforeSavingAndFlagsModelAsChanged(): void
    {
        $order = [];
        $entry = $this->createMock(WalletLedger::class);
        $entry->expects($this->once())->method('setHasDataChanges')->with(true)
            ->willReturnCallback(function () use (&$order, $entry) {
                $order[] = 'flag';
                return $entry;
            });
        $validator = $this->createMock(WalletLedgerEntryValidator::class);
        $validator->expects($this->once())->method('validate')->with($entry)
            ->willReturnCallback(function () use (&$order) {
                $order[] = 'validate';
            });
        $resource = $this->createMock(ResourceModel::class);
        $resource->expects($this->once())->method('save')->with($entry)
            ->willReturnCallback(function () use (&$order, $resource) {
                $order[] = 'save';
                return $resource;
            });

        $repository = new WalletLedgerRepository($resource, $this->createMock(CollectionFactory::class), $validator);
        $this->assertSame($entry, $repository->append($entry));
        $this->assertSame(['validate', 'flag', 'save'], $order);
    }

    public function testInvalidEntryIsNeverSaved(): void
    {
        $validator = $this->createMock(WalletLedgerEntryValidator::class);
        $validator->method('validate')->willThrowException(new InputException(__('bad')));
        $resource = $this->createMock(ResourceModel::class);
        $resource->expects($this->never())->method('save');

        $repository = new WalletLedgerRepository($resource, $this->createMock(CollectionFactory::class), $validator);
        $this->expectException(InputException::class);
        $repository->append($this->createMock(WalletLedger::class));
    }
}

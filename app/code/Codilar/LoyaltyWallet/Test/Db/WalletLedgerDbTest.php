<?php
declare(strict_types=1);

namespace Codilar\LoyaltyWallet\Test\Db;

use Codilar\LoyaltyWallet\Api\Data\WalletLedgerEntryInterface as E;
use Codilar\LoyaltyWallet\Api\WalletLedgerRepositoryInterface;
use Codilar\LoyaltyWallet\Model\ResourceModel\WalletLedger as WalletLedgerResource;
use Codilar\LoyaltyWallet\Model\WalletLedgerFactory;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\LocalizedException;

class WalletLedgerDbTest extends DbTestCase
{
    private WalletLedgerRepositoryInterface $repository;
    private WalletLedgerFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = $this->om->get(WalletLedgerRepositoryInterface::class);
        $this->factory = $this->om->get(WalletLedgerFactory::class);
    }

    private function entry(string $key = 'db-test-1', array $overrides = [])
    {
        return $this->factory->create(['data' => $overrides + [
            E::CUSTOMER_ID => self::CUSTOMER_A, E::CUSTOMER_EMAIL => 'db@test.local',
            E::ENTRY_TYPE => E::TYPE_CREDIT_MANUAL, E::AMOUNT => '12.5', E::BALANCE_AFTER => '12.5',
            E::REASON => 'db test', E::OPERATOR_REF => 'op-db', E::IDEMPOTENCY_KEY => $key,
        ]]);
    }

    private function rowCount(string $key): int
    {
        return (int)$this->connection->fetchOne(
            'SELECT COUNT(*) FROM ' . $this->table('codilar_wallet_ledger') . ' WHERE idempotency_key = ?',
            [$key]
        );
    }

    public function testInsertionPersistsAndReadsBackAsDecimalStrings(): void
    {
        $saved = $this->repository->append($this->entry());
        $this->assertGreaterThan(0, $saved->getEntryId());

        $found = $this->repository->getByIdempotencyKey('db-test-1');
        $this->assertNotNull($found);
        $this->assertSame('12.5000', $found->getAmount());
        $this->assertSame('12.5000', $found->getBalanceAfter());
        $this->assertNotNull($found->getCreatedAt());

        $list = $this->repository->getListByCustomer(self::CUSTOMER_A, 10);
        $this->assertCount(1, $list);
    }

    public function testKeysetPaginationReturnsNewestFirst(): void
    {
        $first = $this->repository->append($this->entry('db-page-1'));
        $second = $this->repository->append($this->entry('db-page-2'));
        $third = $this->repository->append($this->entry('db-page-3'));

        $page = $this->repository->getListByCustomer(self::CUSTOMER_A, 2);
        $this->assertSame([$third->getEntryId(), $second->getEntryId()], array_map(fn ($e) => $e->getEntryId(), $page));
        $next = $this->repository->getListByCustomer(self::CUSTOMER_A, 2, $second->getEntryId());
        $this->assertSame([$first->getEntryId()], array_map(fn ($e) => $e->getEntryId(), $next));
    }

    public function testInvalidEntryIsRejectedAndNothingIsWritten(): void
    {
        try {
            $this->repository->append($this->entry('db-invalid', [E::AMOUNT => '0', E::REASON => '']));
            $this->fail('Expected InputException');
        } catch (InputException $e) {
            $this->assertSame(0, $this->rowCount('db-invalid'));
        }
    }

    public function testDuplicateIdempotencyKeyCannotCreateASecondRow(): void
    {
        $this->repository->append($this->entry('db-dup'));
        try {
            $this->repository->append($this->entry('db-dup'));
            $this->fail('Expected the unique idempotency key to reject the duplicate');
        } catch (AlreadyExistsException | LocalizedException $e) {
            $this->assertSame(1, $this->rowCount('db-dup'));
        }
        $this->assertNotNull($this->repository->getByIdempotencyKey('db-dup'));
    }

    public function testSavedEntryCannotBeUpdatedThroughTheApplication(): void
    {
        $saved = $this->repository->append($this->entry('db-immutable'));
        $saved->setData(E::REASON, 'tampered');
        try {
            $this->repository->append($saved);
            $this->fail('Expected immutability error');
        } catch (LocalizedException $e) {
            $this->assertStringContainsString('immutable', (string)$e->getMessage());
        }
        $this->assertSame('db test', $this->connection->fetchOne(
            'SELECT reason FROM ' . $this->table('codilar_wallet_ledger') . ' WHERE idempotency_key = ?',
            ['db-immutable']
        ));
    }

    public function testSavedEntryCannotBeDeletedThroughTheApplication(): void
    {
        $saved = $this->repository->append($this->entry('db-nodelete'));
        try {
            $this->om->get(WalletLedgerResource::class)->delete($saved);
            $this->fail('Expected delete to be refused');
        } catch (LocalizedException $e) {
            $this->assertSame(1, $this->rowCount('db-nodelete'));
        }
    }
}

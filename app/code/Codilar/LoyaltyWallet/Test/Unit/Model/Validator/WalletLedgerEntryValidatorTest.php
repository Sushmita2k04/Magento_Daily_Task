<?php
declare(strict_types=1);

namespace Codilar\LoyaltyWallet\Test\Unit\Model\Validator;

use Codilar\LoyaltyWallet\Api\Data\WalletLedgerEntryInterface as E;
use Codilar\LoyaltyWallet\Model\Validator\WalletLedgerEntryValidator;
use Codilar\LoyaltyWallet\Model\WalletLedger;
use Magento\Framework\Exception\InputException;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class WalletLedgerEntryValidatorTest extends TestCase
{
    private WalletLedgerEntryValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new WalletLedgerEntryValidator();
    }

    private function entry(array $overrides = []): E
    {
        $entry = (new ObjectManager($this))->getObject(WalletLedger::class);
        $entry->setData($overrides + [
            E::CUSTOMER_ID => 5, E::CUSTOMER_EMAIL => 'a@b.com', E::ENTRY_TYPE => E::TYPE_CREDIT_REFUND,
            E::AMOUNT => '10.5', E::BALANCE_AFTER => '10.5', E::REASON => 'refund',
            E::IDEMPOTENCY_KEY => 'k1',
        ]);
        return $entry;
    }

    public function testValidEntriesPass(): void
    {
        $this->validator->validate($this->entry());
        $this->validator->validate($this->entry([
            E::ENTRY_TYPE => E::TYPE_DEBIT_REDEMPTION, E::AMOUNT => '-4', E::BALANCE_AFTER => '6.5',
        ]));
        $this->validator->validate($this->entry([
            E::ENTRY_TYPE => E::TYPE_CREDIT_MANUAL, E::OPERATOR_REF => 'op-1',
        ]));
        $this->validator->validate($this->entry([
            E::ENTRY_TYPE => E::TYPE_WRITE_OFF, E::AMOUNT => '0', E::MEMO_AMOUNT => '7.25',
        ]));
        $this->addToAssertionCount(1);
    }

    #[DataProvider('invalidProvider')]
    public function testInvalidEntriesAreRejected(array $overrides, string $expectedMessage): void
    {
        try {
            $this->validator->validate($this->entry($overrides));
            $this->fail('Expected InputException');
        } catch (InputException $e) {
            $messages = $e->getErrors()
                ? array_map(static fn ($error) => (string)$error->getMessage(), $e->getErrors())
                : [(string)$e->getMessage()];
            $this->assertContains($expectedMessage, $messages);
        }
    }

    public static function invalidProvider(): array
    {
        return [
            'no customer' => [[E::CUSTOMER_ID => 0], 'A customer id is required.'],
            'bad email' => [[E::CUSTOMER_EMAIL => 'nope'], 'A valid customer email (at most 255 characters) is required.'],
            'blank reason' => [[E::REASON => '  '], 'A reason of 1 to 255 characters is required.'],
            'no key' => [[E::IDEMPOTENCY_KEY => ''], 'An idempotency key of 1 to 128 characters is required.'],
            'unknown type' => [[E::ENTRY_TYPE => 'BOGUS'], 'Unknown wallet entry type.'],
            'zero amount' => [[E::AMOUNT => '0'], 'Amount must not be zero.'],
            'float-like precision' => [[E::AMOUNT => '0.30000000000000004'], 'Amount must be a decimal with at most 4 decimal places.'],
            'credit negative' => [[E::AMOUNT => '-1'], 'This entry type must have a positive amount.'],
            'debit positive' => [[E::ENTRY_TYPE => E::TYPE_CLAWBACK, E::AMOUNT => '1'], 'This entry type must have a negative amount.'],
            'negative balance' => [[E::BALANCE_AFTER => '-0.01'], 'Resulting balance must be a non-negative decimal.'],
            'manual without operator' => [[E::ENTRY_TYPE => E::TYPE_CREDIT_MANUAL], 'Manual entries require an operator reference of at most 64 characters.'],
            'write-off moves balance' => [[E::ENTRY_TYPE => E::TYPE_WRITE_OFF, E::AMOUNT => '5', E::MEMO_AMOUNT => '5'], 'A write-off must not move the balance (amount must be 0).'],
            'write-off no memo' => [[E::ENTRY_TYPE => E::TYPE_WRITE_OFF, E::AMOUNT => '0'], 'A write-off must record a positive written-off amount.'],
            'memo on credit' => [[E::MEMO_AMOUNT => '1'], 'Only a write-off may carry a written-off amount.'],
            'bad order id' => [[E::ORDER_ID => -2], 'The order id must be positive when given.'],
        ];
    }

    public function testAllProblemsAreReportedTogether(): void
    {
        try {
            $this->validator->validate($this->entry([E::CUSTOMER_ID => 0, E::REASON => '', E::AMOUNT => '0']));
            $this->fail('Expected InputException');
        } catch (InputException $e) {
            $this->assertCount(3, $e->getErrors());
        }
    }
}

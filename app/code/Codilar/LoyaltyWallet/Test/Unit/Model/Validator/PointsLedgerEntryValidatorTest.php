<?php
declare(strict_types=1);

namespace Codilar\LoyaltyWallet\Test\Unit\Model\Validator;

use Codilar\LoyaltyWallet\Api\Data\PointsLedgerEntryInterface as E;
use Codilar\LoyaltyWallet\Model\PointsLedger;
use Codilar\LoyaltyWallet\Model\Validator\PointsLedgerEntryValidator;
use Magento\Framework\Exception\InputException;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class PointsLedgerEntryValidatorTest extends TestCase
{
    private PointsLedgerEntryValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new PointsLedgerEntryValidator();
    }

    private function entry(array $overrides = []): E
    {
        $entry = (new ObjectManager($this))->getObject(PointsLedger::class);
        $entry->setData($overrides + [
            E::CUSTOMER_ID => 5, E::CUSTOMER_EMAIL => 'a@b.com', E::ENTRY_TYPE => E::TYPE_ACCRUAL,
            E::POINTS => 100, E::BALANCE_AFTER => 100, E::REASON => 'order', E::LOT_ID => 9,
            E::ACCRUED_AT => '2026-01-01 00:00:00', E::EXPIRES_AT => '2027-01-01 00:00:00',
            E::IDEMPOTENCY_KEY => 'accrual:1',
        ]);
        return $entry;
    }

    public function testValidEntriesPass(): void
    {
        $this->validator->validate($this->entry());
        $this->validator->validate($this->entry([
            E::ENTRY_TYPE => E::TYPE_CONVERSION, E::POINTS => -50, E::BALANCE_AFTER => 50,
            E::WALLET_VALUE => '5.0000', E::WALLET_ENTRY_ID => 3,
        ]));
        $this->validator->validate($this->entry([E::ENTRY_TYPE => E::TYPE_EXPIRY, E::POINTS => -10]));
        $this->validator->validate($this->entry([
            E::ENTRY_TYPE => E::TYPE_ADJUSTMENT, E::POINTS => -3, E::LOT_ID => null,
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
            'bad email' => [[E::CUSTOMER_EMAIL => ''], 'A valid customer email (at most 255 characters) is required.'],
            'blank reason' => [[E::REASON => ''], 'A reason of 1 to 255 characters is required.'],
            'no key' => [[E::IDEMPOTENCY_KEY => ' '], 'An idempotency key of 1 to 128 characters is required.'],
            'unknown type' => [[E::ENTRY_TYPE => 'BOGUS'], 'Unknown points entry type.'],
            'zero points' => [[E::POINTS => 0], 'Points must not be zero.'],
            'negative accrual' => [[E::POINTS => -5], 'An accrual must be positive.'],
            'positive expiry' => [[E::ENTRY_TYPE => E::TYPE_EXPIRY, E::POINTS => 5], 'This entry type must be negative.'],
            'negative balance' => [[E::BALANCE_AFTER => -1], 'Resulting balance must not be negative.'],
            'accrual without lot' => [[E::LOT_ID => null], 'This entry type must reference a points lot.'],
            'expiry before accrual' => [[E::EXPIRES_AT => '2025-01-01 00:00:00'], 'An accrual needs an accrual date and a later expiry date.'],
            'conversion no value' => [[E::ENTRY_TYPE => E::TYPE_CONVERSION, E::POINTS => -5, E::WALLET_ENTRY_ID => 2], 'A conversion must record a positive wallet value.'],
            'conversion no wallet link' => [[E::ENTRY_TYPE => E::TYPE_CONVERSION, E::POINTS => -5, E::WALLET_VALUE => '1'], 'A conversion must reference its wallet credit.'],
        ];
    }
}

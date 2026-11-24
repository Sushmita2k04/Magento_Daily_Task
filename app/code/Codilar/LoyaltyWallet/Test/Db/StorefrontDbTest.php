<?php
declare(strict_types=1);

namespace Codilar\LoyaltyWallet\Test\Db;

use Codilar\LoyaltyWallet\Api\Data\WalletLedgerEntryInterface as W;
use Codilar\LoyaltyWallet\Api\PointsManagementInterface;
use Codilar\LoyaltyWallet\CustomerData\Balance;
use Codilar\LoyaltyWallet\Model\Storefront\BalanceReader;
use Codilar\LoyaltyWallet\Model\Storefront\Money;
use Codilar\LoyaltyWallet\Model\Wallet\WalletMovement;
use Codilar\LoyaltyWallet\Model\Wallet\WalletService;
use DateTimeImmutable;
use DateTimeZone;
use Magento\Customer\Model\Session;

class StorefrontDbTest extends DbTestCase
{
    private PointsManagementInterface $points;
    private WalletService $wallet;
    private BalanceReader $reader;

    protected function setUp(): void
    {
        parent::setUp();
        $this->points = $this->om->get(PointsManagementInterface::class);
        $this->wallet = $this->om->get(WalletService::class);
        $this->reader = $this->om->get(BalanceReader::class);
    }

    private function section(?int $customerId): array
    {
        $session = $this->createStub(Session::class);
        $session->method('isLoggedIn')->willReturn($customerId !== null);
        $session->method('getCustomerId')->willReturn($customerId);
        return (new Balance(
            $session,
            $this->om->get(\Codilar\LoyaltyWallet\Model\Config::class),
            $this->reader,
            $this->om->get(Money::class)
        ))->getSectionData();
    }

    public function testReaderShowsOnlySpendablePointsAndTheNextExpiry(): void
    {
        $this->points->accrue(self::CUSTOMER_A, 'a@example.com', 880101, 'S-1', '100.00');
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));

        $summary = $this->reader->read(self::CUSTOMER_A, $now);
        $this->assertSame(100, $summary->points);
        $this->assertSame(100, $summary->nextExpiry['points']);

        // 13 months on, the lot is past expiry but the daily run has not closed it: stored balance still says 100,
        // the storefront must say 0 and have no next expiry.
        $later = $now->modify('+13 months');
        $summary = $this->reader->read(self::CUSTOMER_A, $later);
        $this->assertSame(0, $summary->points);
        $this->assertNull($summary->nextExpiry);
        $this->assertSame(100, $this->points->getBalance(self::CUSTOMER_A));
    }

    public function testReaderReturnsZerosForACustomerWithNoHistory(): void
    {
        $summary = $this->reader->read(self::CUSTOMER_B);
        $this->assertSame(0, $summary->points);
        $this->assertSame('0.0000', $summary->wallet);
        $this->assertNull($summary->nextExpiry);
    }

    /** AC-07 (data layer): every customer sees their own balances and never another's. */
    public function testSectionDataIsPerCustomerAndNothingForGuests(): void
    {
        $this->points->accrue(self::CUSTOMER_A, 'a@example.com', 880102, 'S-2', '300.00');
        $this->wallet->credit(new WalletMovement(
            self::CUSTOMER_B,
            'b@example.com',
            W::TYPE_CREDIT_REFUND,
            '1234.5',
            'refund',
            'sf:b:1'
        ));

        $a = $this->section(self::CUSTOMER_A);
        $b = $this->section(self::CUSTOMER_B);
        $this->assertSame(300, $a['points']);
        $this->assertSame('₹0.00', $a['wallet']);
        $this->assertSame(0, $b['points']);
        $this->assertSame('₹1,234.50', $b['wallet']);
        $this->assertSame(['is_enabled' => false], $this->section(null));
    }

    public function testMoneyFormattingRoundsHalfUpAndNeverUsesFloats(): void
    {
        $money = $this->om->get(Money::class);
        foreach ([
            '0' => '₹0.00', '25' => '₹25.00', '0.005' => '₹0.01', '0.0049' => '₹0.00',
            '1234.5' => '₹1,234.50', '99999999.9999' => '₹100,000,000.00',
        ] as $in => $out) {
            $this->assertSame($out, $money->format((string)$in), "input $in");
        }
    }
}

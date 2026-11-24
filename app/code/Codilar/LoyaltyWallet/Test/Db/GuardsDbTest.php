<?php
declare(strict_types=1);

namespace Codilar\LoyaltyWallet\Test\Db;

use Codilar\LoyaltyWallet\Api\Data\WalletLedgerEntryInterface as W;
use Codilar\LoyaltyWallet\Api\PointsManagementInterface;
use Codilar\LoyaltyWallet\Model\Wallet\QuoteWalletManager;
use Codilar\LoyaltyWallet\Model\Wallet\WalletMovement;
use Codilar\LoyaltyWallet\Model\Wallet\WalletService;
use Codilar\LoyaltyWallet\Plugin\PreventCustomerDeletion;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;

/**
 * Authorisation and data-protection guards.
 */
class GuardsDbTest extends DbTestCase
{
    private function plugin(): PreventCustomerDeletion
    {
        return $this->om->get(PreventCustomerDeletion::class);
    }

    private function repo(): CustomerRepositoryInterface
    {
        return $this->createStub(CustomerRepositoryInterface::class);
    }

    public function testACustomerWithNoHistoryMayBeDeleted(): void
    {
        $this->assertNull($this->plugin()->beforeDeleteById($this->repo(), self::CUSTOMER_B));
    }

    public function testACustomerWithPointsHistoryCannotBeDeleted(): void
    {
        $this->om->get(PointsManagementInterface::class)->accrue(self::CUSTOMER_A, 'g@example.com', 990601, 'G-1', '10');
        $this->expectException(LocalizedException::class);
        $this->plugin()->beforeDeleteById($this->repo(), self::CUSTOMER_A);
    }

    public function testACustomerWithWalletHistoryCannotBeDeleted(): void
    {
        $this->om->get(WalletService::class)->credit(new WalletMovement(
            self::CUSTOMER_A, 'g@example.com', W::TYPE_CREDIT_MANUAL, '1', 'seed', 'g:seed', null, null, null, 't'
        ));
        $customer = $this->createStub(\Magento\Customer\Api\Data\CustomerInterface::class);
        $customer->method('getId')->willReturn((string)self::CUSTOMER_A);
        $this->expectException(LocalizedException::class);
        $this->plugin()->beforeDelete($this->repo(), $customer);
    }

    // ---- applying the wallet to a cart: whose cart, which state ----------------------------------------------

    private function quote(array $data = []): Quote
    {
        $quote = $this->om->create(Quote::class)->setData($data + [
            'customer_id' => self::CUSTOMER_A, 'store_id' => 1, 'items_count' => 1,
        ]);
        return $quote;
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('unusableCarts')]
    public function testTheWalletCannotBeAppliedToSomeoneElsesGuestOrEmptyCarts(array $quoteData, int $actingAs): void
    {
        $manager = new QuoteWalletManager(
            $this->createConfigStub(),
            $this->createStub(CartRepositoryInterface::class)
        );
        $quote = $this->quote($quoteData);
        try {
            $manager->apply($quote, $actingAs, null);
            $this->fail('Expected refusal');
        } catch (LocalizedException) {
            $this->assertNull($quote->getData('codilar_wallet_use_all'), 'the cart must be left untouched');
        }
    }

    public static function unusableCarts(): array
    {
        return [
            'another customer\'s cart' => [['customer_id' => 990002], 990001],
            'guest cart' => [['customer_id' => null], 990001],
            'not signed in' => [[], 0],
            'empty cart' => [['items_count' => 0], 990001],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('badAmounts')]
    public function testBadAmountsAreRefusedBeforeAnythingIsSaved(string $amount): void
    {
        $repository = $this->createMock(CartRepositoryInterface::class);
        $repository->expects($this->never())->method('save');
        $manager = new QuoteWalletManager($this->createConfigStub(), $repository);
        $this->expectException(LocalizedException::class);
        $manager->apply($this->quote(), self::CUSTOMER_A, $amount);
    }

    public static function badAmounts(): array
    {
        return [[''], ['0'], ['-5'], ['abc'], ['1.23456'], ['1e3'], ['5 OR 1=1']];
    }

    public function testTheWalletCannotBeUsedWhenTheModuleIsDisabled(): void
    {
        $config = $this->createStub(\Codilar\LoyaltyWallet\Model\Config::class);
        $config->method('isEnabled')->willReturn(false);
        $this->expectException(LocalizedException::class);
        (new QuoteWalletManager($config, $this->createStub(CartRepositoryInterface::class)))
            ->apply($this->quote(), self::CUSTOMER_A, null);
    }

    private function createConfigStub(): \Codilar\LoyaltyWallet\Model\Config
    {
        $config = $this->createStub(\Codilar\LoyaltyWallet\Model\Config::class);
        $config->method('isEnabled')->willReturn(true);
        return $config;
    }
}

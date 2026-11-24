<?php
declare(strict_types=1);

namespace Codilar\LoyaltyWallet\ViewModel;

use Codilar\LoyaltyWallet\Model\Config;
use Codilar\LoyaltyWallet\Model\Decimal;
use Codilar\LoyaltyWallet\Model\Storefront\BalanceReader;
use Codilar\LoyaltyWallet\Model\Storefront\Money;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\View\Element\Block\ArgumentInterface;

/**
 * Data for the wallet box on the cart page. The customer comes from the session only.
 */
class CartWallet implements ArgumentInterface
{
    /**
     * @param Config $config
     * @param CustomerSession $customerSession
     * @param CheckoutSession $checkoutSession
     * @param BalanceReader $reader
     * @param Money $money
     */
    public function __construct(
        private readonly Config $config,
        private readonly CustomerSession $customerSession,
        private readonly CheckoutSession $checkoutSession,
        private readonly BalanceReader $reader,
        private readonly Money $money
    ) {
    }

    /**
     * @return bool
     */
    public function isAvailable(): bool
    {
        return $this->config->isEnabled() && $this->customerSession->isLoggedIn();
    }

    /**
     * @return string Wallet balance, decimal string
     */
    public function getBalance(): string
    {
        return $this->reader->read((int)$this->customerSession->getCustomerId())->wallet;
    }

    /**
     * @return string Amount applied on the current cart, decimal string
     */
    public function getAppliedAmount(): string
    {
        return Decimal::fromFloat($this->checkoutSession->getQuote()->getData('codilar_wallet_amount'));
    }

    /**
     * @return bool
     */
    public function hasApplied(): bool
    {
        return Decimal::sign($this->getAppliedAmount()) > 0;
    }

    /**
     * @return bool
     */
    public function canApply(): bool
    {
        return Decimal::sign($this->getBalance()) > 0;
    }

    /**
     * @return string
     */
    public function getMaxPercent(): string
    {
        return rtrim(rtrim($this->config->getMaxRedemptionPercent(), '0'), '.');
    }

    /**
     * @param string $amount
     * @return string
     */
    public function formatMoney(string $amount): string
    {
        return $this->money->format($amount);
    }
}

<?php
declare(strict_types=1);

namespace Codilar\LoyaltyWallet\Model\Wallet;

use Codilar\LoyaltyWallet\Model\Config;
use Codilar\LoyaltyWallet\Model\Decimal;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;

/**
 * Records how much wallet balance the customer wants to use on their cart (FR-08) and recalculates the totals.
 * The amount actually applied is worked out by the quote total collector.
 */
class QuoteWalletManager
{
    /**
     * @param Config $config
     * @param CartRepositoryInterface $quoteRepository
     */
    public function __construct(
        private readonly Config $config,
        private readonly CartRepositoryInterface $quoteRepository
    ) {
    }

    /**
     * @param Quote $quote
     * @param int $customerId Logged-in customer; must own the quote
     * @param string|null $amount Positive decimal string, or null to use as much as allowed
     * @return string The amount applied after caps, decimal string
     * @throws LocalizedException
     */
    public function apply(Quote $quote, int $customerId, ?string $amount): string
    {
        $this->assertUsable($quote, $customerId);
        if ($amount !== null && (!Decimal::isValid($amount) || Decimal::sign($amount) <= 0)) {
            throw new LocalizedException(__('Enter an amount greater than zero, with at most 4 decimal places.'));
        }
        $quote->setData('codilar_wallet_use_all', $amount === null ? 1 : 0);
        $quote->setData('codilar_wallet_requested', $amount === null ? null : Decimal::normalize($amount));
        return $this->recollect($quote);
    }

    /**
     * @param Quote $quote
     * @param int $customerId
     * @return void
     * @throws LocalizedException
     */
    public function remove(Quote $quote, int $customerId): void
    {
        $this->assertUsable($quote, $customerId);
        $quote->setData('codilar_wallet_use_all', 0);
        $quote->setData('codilar_wallet_requested', null);
        $this->recollect($quote);
    }

    /**
     * @param Quote $quote
     * @return string
     */
    private function recollect(Quote $quote): string
    {
        $quote->setTotalsCollectedFlag(false)->collectTotals();
        $this->quoteRepository->save($quote);
        return Decimal::fromFloat($quote->getData('codilar_wallet_amount'));
    }

    /**
     * @param Quote $quote
     * @param int $customerId
     * @return void
     * @throws LocalizedException
     */
    private function assertUsable(Quote $quote, int $customerId): void
    {
        if (!$this->config->isEnabled((int)$quote->getStoreId())) {
            throw new LocalizedException(__('The store wallet is not available.'));
        }
        if ($customerId < 1 || (int)$quote->getCustomerId() !== $customerId) {
            throw new LocalizedException(__('Please sign in to use your store wallet.'));
        }
        if (!$quote->getItemsCount()) {
            throw new LocalizedException(__('Your cart is empty.'));
        }
    }
}

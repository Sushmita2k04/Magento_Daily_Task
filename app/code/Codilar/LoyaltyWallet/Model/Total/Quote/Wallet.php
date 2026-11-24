<?php
declare(strict_types=1);

namespace Codilar\LoyaltyWallet\Model\Total\Quote;

use Codilar\LoyaltyWallet\Model\Config;
use Codilar\LoyaltyWallet\Model\Decimal;
use Codilar\LoyaltyWallet\Model\Storefront\BalanceReader;
use Magento\Quote\Api\Data\ShippingAssignmentInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address\Total;
use Magento\Quote\Model\Quote\Address\Total\AbstractTotal;

/**
 * Applies the wallet amount the customer asked for to the quote, after the grand total is known (FR-08, BR-04).
 * Amount = the least of: what was requested (or everything, if "use all"), the wallet balance, the maximum
 * redemption share of the order total, and the order total itself.
 *
 * The balance is read without a lock here; the real debit happens under lock when the order is placed.
 */
class Wallet extends AbstractTotal
{
    public const CODE = 'codilar_wallet';

    /**
     * @param Config $config
     * @param BalanceReader $balances
     */
    public function __construct(private readonly Config $config, private readonly BalanceReader $balances)
    {
        $this->setCode(self::CODE);
    }

    /**
     * @inheritDoc
     */
    public function collect(Quote $quote, ShippingAssignmentInterface $shippingAssignment, Total $total)
    {
        parent::collect($quote, $shippingAssignment, $total);
        if (!$shippingAssignment->getItems()) {
            return $this; // the address that carries no items (e.g. billing on a shippable quote)
        }

        $amount = $this->calculate($quote, Decimal::fromFloat($total->getGrandTotal()));
        $quote->setData('codilar_wallet_amount', $amount);
        $total->setData('codilar_wallet_amount', $amount);
        if (Decimal::sign($amount) > 0) {
            $grand = (float)bcsub(Decimal::fromFloat($total->getGrandTotal()), $amount, Decimal::SCALE);
            $baseGrand = (float)bcsub(Decimal::fromFloat($total->getBaseGrandTotal()), $amount, Decimal::SCALE);
            $total->setGrandTotal($grand);
            $total->setBaseGrandTotal($baseGrand);
        }
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function fetch(Quote $quote, Total $total)
    {
        $amount = (string)$total->getData('codilar_wallet_amount');
        if ($amount === '' || !Decimal::isValid($amount) || Decimal::sign($amount) <= 0) {
            return [];
        }
        return ['code' => self::CODE, 'title' => __('Store Wallet'), 'value' => -(float)$amount];
    }

    /**
     * @param Quote $quote
     * @param string $grandTotal Decimal string, before the wallet
     * @return string
     */
    private function calculate(Quote $quote, string $grandTotal): string
    {
        $none = '0.0000';
        $useAll = (int)$quote->getData('codilar_wallet_use_all') === 1;
        $requested = $quote->getData('codilar_wallet_requested');
        $customerId = (int)$quote->getCustomerId();
        if (!$this->config->isEnabled((int)$quote->getStoreId())
            || $customerId < 1
            || (!$useAll && ($requested === null || $requested === ''))
            || $quote->getIsMultiShipping()
            || $quote->getQuoteCurrencyCode() !== 'INR'
            || $quote->getBaseCurrencyCode() !== 'INR'
            || Decimal::sign($grandTotal) <= 0
        ) {
            return $none;
        }

        $limit = Decimal::min(
            $this->balances->read($customerId)->wallet,
            bcdiv(bcmul($grandTotal, $this->config->getMaxRedemptionPercent(), 8), '100', Decimal::SCALE)
        );
        $limit = Decimal::min($limit, $grandTotal);
        if (!$useAll) {
            $limit = Decimal::min($limit, Decimal::normalize((string)$requested));
        }
        return Decimal::sign($limit) > 0 ? Decimal::normalize($limit) : $none;
    }
}

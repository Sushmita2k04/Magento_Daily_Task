<?php
declare(strict_types=1);

namespace Codilar\LoyaltyWallet\Model;

use Codilar\LoyaltyWallet\Api\Data\PointsLedgerEntryInterface as E;
use Codilar\LoyaltyWallet\Model\ResourceModel\PointsLedger as ResourceModel;
use Magento\Framework\Model\AbstractModel;

class PointsLedger extends AbstractModel implements E
{
    /**
     * @inheritDoc
     */
    protected function _construct()
    {
        $this->_init(ResourceModel::class);
    }

    public function getEntryId(): ?int
    {
        return $this->getData(E::ENTRY_ID) === null ? null : (int)$this->getData(E::ENTRY_ID);
    }

    public function getCustomerId(): int
    {
        return (int)$this->getData(E::CUSTOMER_ID);
    }

    public function getEntryType(): string
    {
        return (string)$this->getData(E::ENTRY_TYPE);
    }

    public function getPoints(): int
    {
        return (int)$this->getData(E::POINTS);
    }

    public function getBalanceAfter(): int
    {
        return (int)$this->getData(E::BALANCE_AFTER);
    }

    public function getIdempotencyKey(): string
    {
        return (string)$this->getData(E::IDEMPOTENCY_KEY);
    }

    public function getCreatedAt(): ?string
    {
        return $this->getData(E::CREATED_AT);
    }

    public function getCustomerEmail(): string
    {
        return (string)$this->getData(E::CUSTOMER_EMAIL);
    }

    public function getLotId(): ?int
    {
        return $this->getData(E::LOT_ID) === null ? null : (int)$this->getData(E::LOT_ID);
    }

    public function getOrderId(): ?int
    {
        return $this->getData(E::ORDER_ID) === null ? null : (int)$this->getData(E::ORDER_ID);
    }

    public function getWalletEntryId(): ?int
    {
        return $this->getData(E::WALLET_ENTRY_ID) === null ? null : (int)$this->getData(E::WALLET_ENTRY_ID);
    }

    public function getWalletValue(): ?string
    {
        $value = $this->getData(E::WALLET_VALUE);
        return $value === null || $value === '' ? null : (string)$value;
    }

    public function getReason(): string
    {
        return (string)$this->getData(E::REASON);
    }

    public function getAccruedAt(): ?string
    {
        $value = $this->getData(E::ACCRUED_AT);
        return $value === null || $value === '' ? null : (string)$value;
    }

    public function getExpiresAt(): ?string
    {
        $value = $this->getData(E::EXPIRES_AT);
        return $value === null || $value === '' ? null : (string)$value;
    }
}

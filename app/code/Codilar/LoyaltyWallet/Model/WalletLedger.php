<?php
declare(strict_types=1);

namespace Codilar\LoyaltyWallet\Model;

use Codilar\LoyaltyWallet\Api\Data\WalletLedgerEntryInterface as E;
use Codilar\LoyaltyWallet\Model\ResourceModel\WalletLedger as ResourceModel;
use Magento\Framework\Model\AbstractModel;

class WalletLedger extends AbstractModel implements E
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

    public function getAmount(): string
    {
        $value = $this->getData(E::AMOUNT);
        return $value === null ? '' : (string)$value;
    }

    public function getBalanceAfter(): string
    {
        $value = $this->getData(E::BALANCE_AFTER);
        return $value === null ? '' : (string)$value;
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

    public function getMemoAmount(): ?string
    {
        $value = $this->getData(E::MEMO_AMOUNT);
        return $value === null || $value === '' ? null : (string)$value;
    }

    public function getReason(): string
    {
        return (string)$this->getData(E::REASON);
    }

    public function getOrderId(): ?int
    {
        return $this->getData(E::ORDER_ID) === null ? null : (int)$this->getData(E::ORDER_ID);
    }

    public function getCreditmemoId(): ?int
    {
        return $this->getData(E::CREDITMEMO_ID) === null ? null : (int)$this->getData(E::CREDITMEMO_ID);
    }

    public function getOperatorRef(): ?string
    {
        $value = $this->getData(E::OPERATOR_REF);
        return $value === null || $value === '' ? null : (string)$value;
    }
}

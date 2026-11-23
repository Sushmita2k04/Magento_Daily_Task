<?php
declare(strict_types=1);

namespace Codilar\LoyaltyWallet\Model\Wallet;

use Codilar\LoyaltyWallet\Api\Data\WalletLedgerEntryInterface as W;
use Codilar\LoyaltyWallet\Api\WalletLedgerRepositoryInterface;
use Codilar\LoyaltyWallet\Model\Decimal;
use Codilar\LoyaltyWallet\Model\Locking\BalanceLocker;
use Codilar\LoyaltyWallet\Model\Locking\TransactionRunner;
use Codilar\LoyaltyWallet\Model\WalletLedgerFactory;
use Magento\Framework\Exception\LocalizedException;

/**
 * Posts wallet movements (FR-06 to FR-09). Every call locks the customer, checks the idempotency key, appends the
 * ledger row and updates the balance in one transaction. The balance can never go below zero (BR-02).
 */
class WalletService
{
    private const CREDIT_TYPES = [
        W::TYPE_CREDIT_REFUND, W::TYPE_CREDIT_CANCELLATION, W::TYPE_CREDIT_MANUAL, W::TYPE_REDEMPTION_RELEASE,
    ];
    private const DEBIT_TYPES = [W::TYPE_DEBIT_MANUAL, W::TYPE_DEBIT_REDEMPTION];

    /**
     * @param BalanceLocker $locker
     * @param TransactionRunner $transaction
     * @param WalletLedgerRepositoryInterface $ledger
     * @param WalletLedgerFactory $entryFactory
     */
    public function __construct(
        private readonly BalanceLocker $locker,
        private readonly TransactionRunner $transaction,
        private readonly WalletLedgerRepositoryInterface $ledger,
        private readonly WalletLedgerFactory $entryFactory
    ) {
    }

    /**
     * Add money to the wallet (refund, cancellation, manual credit, redemption release).
     *
     * @param WalletMovement $movement
     * @return W
     * @throws LocalizedException
     */
    public function credit(WalletMovement $movement): W
    {
        $this->assertValid($movement, self::CREDIT_TYPES);
        return $this->transaction->run(fn (): W => $this->postLocked($movement, 1));
    }

    /**
     * Take money from the wallet (redemption, manual debit). Refused when the balance is not enough.
     *
     * @param WalletMovement $movement
     * @return W
     * @throws LocalizedException
     */
    public function debit(WalletMovement $movement): W
    {
        $this->assertValid($movement, self::DEBIT_TYPES);
        return $this->transaction->run(fn (): W => $this->postLocked($movement, -1));
    }

    /**
     * Take back money that was credited for points which have since been reversed (BR-03). Takes what the wallet
     * holds, up to the amount asked for; any shortfall is recorded as a written-off adjustment, never as a negative
     * balance.
     *
     * @param WalletMovement $movement Entry type must be CLAWBACK
     * @return ClawbackResult
     * @throws LocalizedException
     */
    public function clawback(WalletMovement $movement): ClawbackResult
    {
        $this->assertValid($movement, [W::TYPE_CLAWBACK]);
        return $this->transaction->run(fn (): ClawbackResult => $this->clawbackLocked($movement));
    }

    /**
     * @param WalletMovement $movement
     * @param int $direction 1 credit, -1 debit
     * @return W
     * @throws LocalizedException
     */
    private function postLocked(WalletMovement $movement, int $direction): W
    {
        $balances = $this->locker->lock($movement->customerId);
        $existing = $this->findReplay($movement->idempotencyKey, $movement->customerId);
        if ($existing !== null) {
            return $existing;
        }
        $amount = Decimal::normalize($movement->amount);
        $balance = $direction > 0
            ? bcadd($balances['wallet'], $amount, Decimal::SCALE)
            : bcsub($balances['wallet'], $amount, Decimal::SCALE);
        if (Decimal::sign($balance) < 0) {
            throw new LocalizedException(__('The wallet balance is not enough for this amount.'));
        }
        $entry = $this->append(
            $movement,
            $movement->entryType,
            $direction > 0 ? $amount : '-' . $amount,
            $balance,
            $movement->idempotencyKey
        );
        $this->locker->setWalletBalance($movement->customerId, $balance);
        return $entry;
    }

    /**
     * @param WalletMovement $movement
     * @return ClawbackResult
     * @throws LocalizedException
     */
    private function clawbackLocked(WalletMovement $movement): ClawbackResult
    {
        $balances = $this->locker->lock($movement->customerId);
        $clawKey = $movement->idempotencyKey . ':clawback';
        $writeOffKey = $movement->idempotencyKey . ':writeoff';
        $claw = $this->findReplay($clawKey, $movement->customerId);
        $writeOff = $this->findReplay($writeOffKey, $movement->customerId);
        if ($claw !== null || $writeOff !== null) {
            return new ClawbackResult(
                $claw === null ? '0.0000' : ltrim(Decimal::normalize($claw->getAmount()), '-'),
                $writeOff === null ? '0.0000' : Decimal::normalize((string)$writeOff->getMemoAmount()),
                $balances['wallet'],
                true
            );
        }

        $requested = Decimal::normalize($movement->amount);
        $available = $balances['wallet'];
        $taken = bccomp($available, $requested, Decimal::SCALE) < 0 ? $available : $requested;
        $residual = bcsub($requested, $taken, Decimal::SCALE);
        $balance = bcsub($available, $taken, Decimal::SCALE);

        if (Decimal::sign($taken) > 0) {
            $this->append($movement, W::TYPE_CLAWBACK, '-' . $taken, $balance, $clawKey);
        }
        if (Decimal::sign($residual) > 0) {
            $this->append(
                $movement,
                W::TYPE_WRITE_OFF,
                '0.0000',
                $balance,
                $writeOffKey,
                $residual,
                sprintf('%s (written off %s: wallet balance too low)', mb_substr($movement->reason, 0, 180), $residual)
            );
        }
        $this->locker->setWalletBalance($movement->customerId, $balance);
        return new ClawbackResult($taken, $residual, $balance);
    }

    /**
     * @param WalletMovement $movement
     * @param string $type
     * @param string $amount Signed
     * @param string $balanceAfter
     * @param string $key
     * @param string|null $memo
     * @param string|null $reason
     * @return W
     * @throws LocalizedException
     */
    private function append(
        WalletMovement $movement,
        string $type,
        string $amount,
        string $balanceAfter,
        string $key,
        ?string $memo = null,
        ?string $reason = null
    ): W {
        return $this->ledger->append($this->entryFactory->create(['data' => [
            W::CUSTOMER_ID => $movement->customerId,
            W::CUSTOMER_EMAIL => $movement->customerEmail,
            W::ENTRY_TYPE => $type,
            W::AMOUNT => $amount,
            W::MEMO_AMOUNT => $memo,
            W::BALANCE_AFTER => $balanceAfter,
            W::REASON => $reason ?? $movement->reason,
            W::ORDER_ID => $movement->orderId,
            W::ORDER_INCREMENT_ID => $movement->orderIncrementId,
            W::CREDITMEMO_ID => $movement->creditmemoId,
            W::OPERATOR_REF => $movement->operatorRef,
            W::IDEMPOTENCY_KEY => $key,
        ]]));
    }

    /**
     * A key that already exists is a replay, unless it belongs to a different customer, which is a caller bug.
     *
     * @param string $key
     * @param int $customerId
     * @return W|null
     * @throws LocalizedException
     */
    private function findReplay(string $key, int $customerId): ?W
    {
        $existing = $this->ledger->getByIdempotencyKey($key);
        if ($existing !== null && $existing->getCustomerId() !== $customerId) {
            throw new LocalizedException(__('That request key was already used for a different customer.'));
        }
        return $existing;
    }

    /**
     * @param WalletMovement $movement
     * @param string[] $allowedTypes
     * @return void
     * @throws LocalizedException
     */
    private function assertValid(WalletMovement $movement, array $allowedTypes): void
    {
        if (!in_array($movement->entryType, $allowedTypes, true)) {
            throw new LocalizedException(__('This operation does not accept entry type "%1".', $movement->entryType));
        }
        if (!Decimal::isValid($movement->amount) || Decimal::sign($movement->amount) <= 0) {
            throw new LocalizedException(
                __('The amount must be a positive number with at most 4 decimal places.')
            );
        }
    }
}

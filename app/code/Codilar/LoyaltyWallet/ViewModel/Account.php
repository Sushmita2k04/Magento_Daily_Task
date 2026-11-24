<?php
declare(strict_types=1);

namespace Codilar\LoyaltyWallet\ViewModel;

use Codilar\LoyaltyWallet\Api\Data\PointsLedgerEntryInterface as P;
use Codilar\LoyaltyWallet\Api\Data\WalletLedgerEntryInterface as W;
use Codilar\LoyaltyWallet\Api\PointsLedgerRepositoryInterface;
use Codilar\LoyaltyWallet\Api\WalletLedgerRepositoryInterface;
use Codilar\LoyaltyWallet\Model\Config;
use Codilar\LoyaltyWallet\Model\Decimal;
use Codilar\LoyaltyWallet\Model\Storefront\BalanceReader;
use Codilar\LoyaltyWallet\Model\Storefront\BalanceSummary;
use Codilar\LoyaltyWallet\Model\Storefront\Money;
use Magento\Customer\Model\Session;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;

/**
 * Data for the account page. The customer always comes from the session, never from the request.
 */
class Account implements ArgumentInterface
{
    public const PAGE_SIZE = 10;

    private const POINTS_LABELS = [
        P::TYPE_ACCRUAL => 'Earned',
        P::TYPE_REVERSAL => 'Reversed',
        P::TYPE_CONVERSION => 'Converted to wallet',
        P::TYPE_EXPIRY => 'Expired',
        P::TYPE_ADJUSTMENT => 'Adjustment',
    ];
    private const WALLET_LABELS = [
        W::TYPE_CREDIT_CONVERSION => 'Converted from points',
        W::TYPE_CREDIT_REFUND => 'Refund',
        W::TYPE_CREDIT_CANCELLATION => 'Order cancelled',
        W::TYPE_CREDIT_MANUAL => 'Credit',
        W::TYPE_DEBIT_REDEMPTION => 'Used on an order',
        W::TYPE_DEBIT_MANUAL => 'Debit',
        W::TYPE_CLAWBACK => 'Taken back',
        W::TYPE_WRITE_OFF => 'Adjustment',
        W::TYPE_REDEMPTION_RELEASE => 'Returned after a failed order',
    ];

    private ?BalanceSummary $summary = null;

    /**
     * @param Session $session
     * @param Config $config
     * @param BalanceReader $reader
     * @param Money $money
     * @param PointsLedgerRepositoryInterface $pointsLedger
     * @param WalletLedgerRepositoryInterface $walletLedger
     * @param TimezoneInterface $timezone
     * @param RequestInterface $request
     */
    public function __construct(
        private readonly Session $session,
        private readonly Config $config,
        private readonly BalanceReader $reader,
        private readonly Money $money,
        private readonly PointsLedgerRepositoryInterface $pointsLedger,
        private readonly WalletLedgerRepositoryInterface $walletLedger,
        private readonly TimezoneInterface $timezone,
        private readonly RequestInterface $request
    ) {
    }

    /**
     * @return BalanceSummary
     */
    public function getSummary(): BalanceSummary
    {
        return $this->summary ??= $this->reader->read($this->customerId());
    }

    /**
     * @return string
     */
    public function formatMoney(string $amount): string
    {
        return $this->money->format($amount);
    }

    /**
     * @return int
     */
    public function getMinConversionPoints(): int
    {
        return $this->config->getMinConversionPoints();
    }

    /**
     * @return string Points needed for 1.00 of wallet money
     */
    public function getPointsPerWalletUnit(): string
    {
        return rtrim(rtrim($this->config->getPointsPerWalletUnit(), '0'), '.');
    }

    /**
     * A fresh key per page view: submitting the same form twice converts once.
     *
     * @return string
     */
    public function newRequestKey(): string
    {
        return bin2hex(random_bytes(16));
    }

    /**
     * @return array{entries: P[], next: int|null}
     */
    public function getPointsHistory(): array
    {
        $rows = $this->pointsLedger->getListByCustomer(
            $this->customerId(),
            self::PAGE_SIZE + 1,
            $this->cursor('pb')
        );
        return $this->page($rows);
    }

    /**
     * @return array{entries: W[], next: int|null}
     */
    public function getWalletHistory(): array
    {
        $rows = $this->walletLedger->getListByCustomer(
            $this->customerId(),
            self::PAGE_SIZE + 1,
            $this->cursor('wb')
        );
        return $this->page($rows);
    }

    /**
     * @param P $entry
     * @return string
     */
    public function pointsLabel(P $entry): string
    {
        return (string)__(self::POINTS_LABELS[$entry->getEntryType()] ?? 'Adjustment');
    }

    /**
     * @param W $entry
     * @return string
     */
    public function walletLabel(W $entry): string
    {
        return (string)__(self::WALLET_LABELS[$entry->getEntryType()] ?? 'Adjustment');
    }

    /**
     * @param string|null $utc Database timestamp (UTC)
     * @return string
     */
    public function formatDate(?string $utc): string
    {
        return $utc === null ? '' : $this->timezone->formatDate($utc, \IntlDateFormatter::MEDIUM, false);
    }

    /**
     * Signed display of a wallet amount: "+₹5.00" / "-₹5.00"; a write-off shows the amount that was not taken back.
     *
     * @param W $entry
     * @return string
     */
    public function walletAmountText(W $entry): string
    {
        if ($entry->getEntryType() === W::TYPE_WRITE_OFF) {
            return '—';
        }
        $amount = Decimal::normalize($entry->getAmount());
        return (Decimal::sign($amount) < 0 ? '-' : '+') . $this->money->format(ltrim($amount, '-'));
    }

    /**
     * @return int
     */
    private function customerId(): int
    {
        return (int)$this->session->getCustomerId();
    }

    /**
     * @param string $param
     * @return int|null
     */
    private function cursor(string $param): ?int
    {
        $value = (string)$this->request->getParam($param);
        return ctype_digit($value) && (int)$value > 0 ? (int)$value : null;
    }

    /**
     * @param array $rows One more than the page size was requested, to know whether another page exists
     * @return array{entries: array, next: int|null}
     */
    private function page(array $rows): array
    {
        $more = count($rows) > self::PAGE_SIZE;
        $entries = array_slice($rows, 0, self::PAGE_SIZE);
        $last = $entries ? end($entries) : null;
        return ['entries' => $entries, 'next' => $more && $last ? $last->getEntryId() : null];
    }
}

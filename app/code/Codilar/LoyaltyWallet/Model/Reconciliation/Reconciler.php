<?php
declare(strict_types=1);

namespace Codilar\LoyaltyWallet\Model\Reconciliation;

use Magento\Framework\App\ResourceConnection;

/**
 * Read-only check that the stored balances, lots and running balances all agree with the ledgers (BR-06, NFR-05).
 * It never changes data: a mismatch means a bug or direct tampering and needs a person to look at it.
 */
class Reconciler
{
    /**
     * @param ResourceConnection $resource
     */
    public function __construct(private readonly ResourceConnection $resource)
    {
    }

    /**
     * @param int|null $customerId Limit the check to one customer
     * @param int $limit Maximum findings returned per check
     * @return array<int, array{check: string, customer_id: int, expected: string, actual: string}>
     */
    public function run(?int $customerId = null, int $limit = 200): array
    {
        $findings = [];
        foreach ($this->checks() as $check => $sql) {
            $rows = $this->resource->getConnection()->fetchAll(
                'SELECT * FROM (' . $sql . ') x WHERE (:cid IS NULL OR customer_id = :cid) LIMIT ' . max(1, $limit),
                ['cid' => $customerId]
            );
            foreach ($rows as $row) {
                $findings[] = [
                    'check' => $check,
                    'customer_id' => (int)$row['customer_id'],
                    'expected' => (string)$row['expected'],
                    'actual' => (string)$row['actual'],
                ];
            }
        }
        return $findings;
    }

    /**
     * Each query returns customer_id, expected, actual for every disagreement.
     *
     * @return array<string, string>
     */
    private function checks(): array
    {
        $pl = $this->t('codilar_loyalty_points_ledger');
        $pb = $this->t('codilar_loyalty_points_balance');
        $lots = $this->t('codilar_loyalty_points_lot');
        $wl = $this->t('codilar_wallet_ledger');
        $wb = $this->t('codilar_wallet_balance');

        return [
            'points balance differs from points ledger' => "
                SELECT c.customer_id, COALESCE(l.s, 0) AS expected, COALESCE(b.points_balance, 0) AS actual
                FROM (SELECT customer_id FROM $pl UNION SELECT customer_id FROM $pb) c
                LEFT JOIN (SELECT customer_id, SUM(points) s FROM $pl GROUP BY customer_id) l USING (customer_id)
                LEFT JOIN $pb b USING (customer_id)
                WHERE COALESCE(l.s, 0) <> COALESCE(b.points_balance, 0)",
            'wallet balance differs from wallet ledger' => "
                SELECT c.customer_id, COALESCE(l.s, 0) AS expected, COALESCE(b.balance, 0) AS actual
                FROM (SELECT customer_id FROM $wl UNION SELECT customer_id FROM $wb) c
                LEFT JOIN (SELECT customer_id, SUM(amount) s FROM $wl GROUP BY customer_id) l USING (customer_id)
                LEFT JOIN $wb b USING (customer_id)
                WHERE COALESCE(l.s, 0) <> COALESCE(b.balance, 0)",
            'open lots differ from points balance' => "
                SELECT b.customer_id, b.points_balance AS expected, COALESCE(l.s, 0) AS actual
                FROM $pb b
                LEFT JOIN (SELECT customer_id, SUM(points_remaining) s FROM $lots
                           WHERE expired_at IS NULL GROUP BY customer_id) l USING (customer_id)
                WHERE b.points_balance <> COALESCE(l.s, 0)",
            'points entry balance_after is not the running total' => "
                SELECT customer_id, running AS expected, balance_after AS actual FROM (
                    SELECT customer_id, balance_after,
                           SUM(points) OVER (PARTITION BY customer_id ORDER BY entry_id) AS running
                    FROM $pl) r WHERE running <> balance_after",
            'wallet entry balance_after is not the running total' => "
                SELECT customer_id, running AS expected, balance_after AS actual FROM (
                    SELECT customer_id, balance_after,
                           SUM(amount) OVER (PARTITION BY customer_id ORDER BY entry_id) AS running
                    FROM $wl) r WHERE running <> balance_after",
            'conversion wallet credit differs from its points rows' => "
                SELECT w.customer_id, w.amount AS expected, COALESCE(p.v, 0) AS actual
                FROM $wl w
                LEFT JOIN (SELECT wallet_entry_id, SUM(wallet_value) v FROM $pl
                           WHERE entry_type = 'CONVERSION' GROUP BY wallet_entry_id) p
                       ON p.wallet_entry_id = w.entry_id
                WHERE w.entry_type = 'CREDIT_CONVERSION' AND w.amount <> COALESCE(p.v, 0)",
        ];
    }

    /**
     * @param string $name
     * @return string
     */
    private function t(string $name): string
    {
        return $this->resource->getTableName($name);
    }
}

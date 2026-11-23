<?php
declare(strict_types=1);

namespace Codilar\LoyaltyWallet\Test\Db;

use Magento\Framework\App\Bootstrap;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\ObjectManagerInterface;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Wraps each test in a transaction on the application connection and rolls it back afterwards.
 */
abstract class DbTestCase extends TestCase
{
    protected const CUSTOMER_A = 990001;
    protected const CUSTOMER_B = 990002;

    protected ObjectManagerInterface $om;
    protected AdapterInterface $connection;

    protected function setUp(): void
    {
        $this->om = Bootstrap::create(BP, $_SERVER)->getObjectManager();
        $this->connection = $this->om->get(ResourceConnection::class)->getConnection();
        $this->connection->beginTransaction();
    }

    protected function tearDown(): void
    {
        while ($this->connection->getTransactionLevel() > 0) {
            $this->connection->rollBack();
        }
        // A rolled-back insert still advances AUTO_INCREMENT. Setting it to 1 makes MySQL use max(id) + 1, so the
        // real shop's next order, invoice and credit memo numbers are not shifted by test runs.
        foreach (['sales_order', 'sales_invoice', 'sales_creditmemo'] as $table) {
            $this->connection->query('ALTER TABLE ' . $this->table($table) . ' AUTO_INCREMENT = 1');
        }
    }

    /**
     * A second, independent database session (its own transaction) for lock tests.
     */
    protected function secondSession(int $lockWaitSeconds = 1): PDO
    {
        $db = $this->om->get(\Magento\Framework\App\DeploymentConfig::class)
            ->get('db/connection/default');
        $host = $db['host'];
        $dsn = str_contains($host, '/')
            ? 'mysql:unix_socket=' . $host . ';dbname=' . $db['dbname']
            : 'mysql:host=' . $host . ';dbname=' . $db['dbname'];
        $pdo = new PDO($dsn, $db['username'], $db['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $pdo->exec('SET SESSION innodb_lock_wait_timeout = ' . $lockWaitSeconds);
        $pdo->beginTransaction();
        return $pdo;
    }

    protected function table(string $name): string
    {
        return $this->om->get(ResourceConnection::class)->getTableName($name);
    }

    /**
     * BR-06 / NFR-05: both stored balances must equal the sum of their ledger, and no lot may hold more than the
     * points balance it backs.
     */
    protected function assertLedgersReconcile(int $customerId): void
    {
        $points = (int)$this->connection->fetchOne(
            'SELECT COALESCE(SUM(points), 0) FROM ' . $this->table('codilar_loyalty_points_ledger')
            . ' WHERE customer_id = ?',
            [$customerId]
        );
        $storedPoints = (int)$this->connection->fetchOne(
            'SELECT points_balance FROM ' . $this->table('codilar_loyalty_points_balance') . ' WHERE customer_id = ?',
            [$customerId]
        );
        $this->assertSame($points, $storedPoints, 'Points balance must equal the sum of the points ledger');

        $wallet = (string)$this->connection->fetchOne(
            'SELECT COALESCE(SUM(amount), 0) FROM ' . $this->table('codilar_wallet_ledger') . ' WHERE customer_id = ?',
            [$customerId]
        );
        $storedWallet = (string)$this->connection->fetchOne(
            'SELECT balance FROM ' . $this->table('codilar_wallet_balance') . ' WHERE customer_id = ?',
            [$customerId]
        );
        $this->assertSame(
            bcadd($wallet, '0', 4),
            bcadd($storedWallet ?: '0', '0', 4),
            'Wallet balance must equal the sum of the wallet ledger'
        );

        $lots = (int)$this->connection->fetchOne(
            'SELECT COALESCE(SUM(points_remaining), 0) FROM ' . $this->table('codilar_loyalty_points_lot')
            . ' WHERE customer_id = ? AND expired_at IS NULL',
            [$customerId]
        );
        $this->assertSame($storedPoints, $lots, 'Open lots must add up to the points balance');
    }

    protected function lotRemaining(int $lotId): int
    {
        return (int)$this->connection->fetchOne(
            'SELECT points_remaining FROM ' . $this->table('codilar_loyalty_points_lot') . ' WHERE lot_id = ?',
            [$lotId]
        );
    }
}

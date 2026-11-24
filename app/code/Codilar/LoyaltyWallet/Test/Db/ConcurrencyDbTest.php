<?php
declare(strict_types=1);

namespace Codilar\LoyaltyWallet\Test\Db;

use Codilar\LoyaltyWallet\Api\Data\WalletLedgerEntryInterface as W;
use Codilar\LoyaltyWallet\Api\PointsManagementInterface;
use Codilar\LoyaltyWallet\Model\Reconciliation\Reconciler;
use Codilar\LoyaltyWallet\Model\Wallet\WalletMovement;
use Codilar\LoyaltyWallet\Model\Wallet\WalletService;
use Magento\Framework\App\Bootstrap;
use Magento\Framework\App\ResourceConnection;
use PHPUnit\Framework\TestCase;

/**
 * Real concurrency (NFR-01, AC-03, AC-05): separate PHP processes with their own database connections race on the
 * same customer. Unlike the other DB tests this commits real rows, so it uses its own customer id and removes
 * everything it wrote afterwards.
 */
class ConcurrencyDbTest extends TestCase
{
    private const CUSTOMER = 990100;

    private ResourceConnection $resource;

    protected function setUp(): void
    {
        $om = Bootstrap::create(BP, $_SERVER)->getObjectManager();
        $this->resource = $om->get(ResourceConnection::class);
        $this->purge();
    }

    protected function tearDown(): void
    {
        $this->purge();
    }

    private function purge(): void
    {
        $c = $this->resource->getConnection();
        foreach (['codilar_loyalty_points_ledger', 'codilar_loyalty_points_lot', 'codilar_loyalty_points_balance',
                     'codilar_wallet_ledger', 'codilar_wallet_balance'] as $table) {
            $c->delete($this->resource->getTableName($table), ['customer_id = ?' => self::CUSTOMER]);
        }
    }

    /**
     * @param string $action
     * @param array<int, string[]> $jobs One argument list per worker
     * @return array<int, array>
     */
    private function race(string $action, array $jobs): array
    {
        $startAt = microtime(true) + 3.0;
        $procs = [];
        foreach ($jobs as $i => $args) {
            $cmd = array_merge([PHP_BINARY, __DIR__ . '/Support/worker.php', $action, (string)$startAt], $args);
            $procs[$i] = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
            $procs[$i] = ['proc' => $procs[$i], 'pipes' => $pipes];
        }
        $results = [];
        foreach ($procs as $i => $p) {
            $out = stream_get_contents($p['pipes'][1]);
            stream_get_contents($p['pipes'][2]);
            proc_close($p['proc']);
            $lines = array_filter(explode("\n", trim($out)));
            $results[$i] = json_decode((string)end($lines), true) ?? ['ok' => false, 'error' => 'no output: ' . $out];
        }
        return $results;
    }

    private function fund(string $amount): void
    {
        $om = Bootstrap::create(BP, $_SERVER)->getObjectManager();
        $om->get(WalletService::class)->credit(new WalletMovement(
            self::CUSTOMER, 'cc@example.com', W::TYPE_CREDIT_MANUAL, $amount, 'seed', 'cc:seed', null, null, null, 't'
        ));
    }

    private function balance(): string
    {
        $c = $this->resource->getConnection();
        return bcadd((string)$c->fetchOne(
            'SELECT balance FROM ' . $this->resource->getTableName('codilar_wallet_balance') . ' WHERE customer_id = ?',
            [self::CUSTOMER]
        ), '0', 4);
    }

    /** AC-03: six simultaneous redemptions of 40 against a balance of 100: exactly two can win. */
    public function testSimultaneousRedemptionsNeverOverspend(): void
    {
        $this->fund('100');
        $jobs = [];
        for ($i = 1; $i <= 6; $i++) {
            $jobs[] = [(string)self::CUSTOMER, '40', 'race-' . $i];
        }
        $results = $this->race('debit', $jobs);

        $won = count(array_filter(array_column($results, 'ok')));
        $this->assertSame(2, $won, json_encode($results));
        $this->assertSame('20.0000', $this->balance());
        $om = Bootstrap::create(BP, $_SERVER)->getObjectManager();
        $this->assertSame([], $om->get(Reconciler::class)->run(self::CUSTOMER));
    }

    /** The same conversion request sent six times at once converts once; the others see the first result. */
    public function testSimultaneousIdenticalConversionsConvertOnce(): void
    {
        $om = Bootstrap::create(BP, $_SERVER)->getObjectManager();
        $om->get(PointsManagementInterface::class)->accrue(self::CUSTOMER, 'cc@example.com', 990110, 'CC-1', '100');
        $jobs = array_fill(0, 6, [(string)self::CUSTOMER, '50', 'same-key']);
        $results = $this->race('convert', $jobs);

        $this->assertCount(6, array_filter(array_column($results, 'ok')), json_encode($results));
        $this->assertCount(1, array_filter(array_column($results, 'replayed'), fn ($r) => $r === false));
        $this->assertSame('5.0000', $this->balance());
        $this->assertSame(50, $om->get(PointsManagementInterface::class)->getBalance(self::CUSTOMER));
        $this->assertSame([], $om->get(Reconciler::class)->run(self::CUSTOMER));
    }

    /** Different conversion requests racing for more points than exist: the balance never goes negative. */
    public function testSimultaneousConversionsCannotConvertMoreThanTheBalance(): void
    {
        $om = Bootstrap::create(BP, $_SERVER)->getObjectManager();
        $om->get(PointsManagementInterface::class)->accrue(self::CUSTOMER, 'cc@example.com', 990111, 'CC-2', '100');
        $jobs = [];
        for ($i = 1; $i <= 4; $i++) {
            $jobs[] = [(string)self::CUSTOMER, '40', 'diff-' . $i];
        }
        $results = $this->race('convert', $jobs);

        $this->assertSame(2, count(array_filter(array_column($results, 'ok'))), json_encode($results));
        $this->assertSame(20, $om->get(PointsManagementInterface::class)->getBalance(self::CUSTOMER));
        $this->assertSame('8.0000', $this->balance());
        $this->assertSame([], $om->get(Reconciler::class)->run(self::CUSTOMER));
    }

    /** AC-05: expiry processes started together expire each lot exactly once. */
    public function testSimultaneousExpiryRunsExpireOnce(): void
    {
        $om = Bootstrap::create(BP, $_SERVER)->getObjectManager();
        $om->get(PointsManagementInterface::class)->accrue(self::CUSTOMER, 'cc@example.com', 990112, 'CC-3', '70');
        $this->resource->getConnection()->update(
            $this->resource->getTableName('codilar_loyalty_points_lot'),
            ['expires_at' => '2020-01-01 00:00:00'],
            ['customer_id = ?' => self::CUSTOMER]
        );
        $results = $this->race('expire', array_fill(0, 4, ['x']));

        $this->assertCount(4, array_filter(array_column($results, 'ok')), json_encode($results));
        $expiry = (int)$this->resource->getConnection()->fetchOne(
            'SELECT COUNT(*) FROM ' . $this->resource->getTableName('codilar_loyalty_points_ledger')
            . " WHERE customer_id = ? AND entry_type = 'EXPIRY'",
            [self::CUSTOMER]
        );
        $this->assertSame(1, $expiry);
        $this->assertSame(0, $om->get(PointsManagementInterface::class)->getBalance(self::CUSTOMER));
        $this->assertSame([], $om->get(Reconciler::class)->run(self::CUSTOMER));
    }
}

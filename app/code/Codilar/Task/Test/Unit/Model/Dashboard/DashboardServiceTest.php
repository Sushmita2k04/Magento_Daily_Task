<?php
declare(strict_types=1);

namespace Codilar\Task\Test\Unit\Model\Dashboard;

use Codilar\Task\Model\Dashboard\DashboardFilter;
use Codilar\Task\Model\Dashboard\DashboardService;
use Codilar\Task\Model\Dashboard\DashboardStatistics;
use Codilar\Task\Model\Dashboard\TaskRowMapper;
use Codilar\Task\Model\ResourceModel\Task\Collection;
use Codilar\Task\Model\ResourceModel\Task\CollectionFactory;
use Codilar\Task\Model\Task;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class DashboardServiceTest extends TestCase
{
    public function testBuildsPayloadFromStatisticsAndTwoLimitedLists(): void
    {
        $filter = new DashboardFilter();
        $stats = $this->createMock(DashboardStatistics::class);
        $stats->method('getToday')->willReturn('2026-11-19');
        $stats->method('getStatistics')->with($filter)->willReturn(['summary' => ['total' => 2]]);

        $recentTask = (new ObjectManager($this))->getObject(Task::class);
        $overdueTask = (new ObjectManager($this))->getObject(Task::class);

        $recent = $this->createMock(Collection::class);
        $recent->expects($this->once())->method('addDashboardFilter')->with($filter)->willReturnSelf();
        $recent->expects($this->never())->method('addOverdueFilter');
        $recent->method('setOrder')->willReturnSelf();
        $recent->expects($this->once())->method('setPageSize')->with(5)->willReturnSelf();
        $recent->method('getItems')->willReturn([$recentTask]);
        $recent->expects($this->never())->method('getSize');

        $overdue = $this->createMock(Collection::class);
        $overdue->expects($this->once())->method('addDashboardFilter')->with($filter)->willReturnSelf();
        $overdue->expects($this->once())->method('addOverdueFilter')->with('2026-11-19')->willReturnSelf();
        $overdue->method('setOrder')->willReturnSelf();
        $overdue->expects($this->once())->method('setPageSize')->with(5)->willReturnSelf();
        $overdue->method('getItems')->willReturn([$overdueTask]);
        $overdue->expects($this->never())->method('getSize');

        $factory = $this->createMock(CollectionFactory::class);
        $factory->method('create')->willReturnOnConsecutiveCalls($recent, $overdue);

        $mapper = $this->createMock(TaskRowMapper::class);
        $mapper->method('map')->willReturnCallback(
            fn($task, $today) => ['task' => spl_object_id($task), 'today' => $today]
        );

        $result = (new DashboardService($stats, $factory, $mapper))->getDashboard($filter);

        $this->assertSame($filter->toArray(), $result['filters']);
        $this->assertSame('2026-11-19', $result['today']);
        $this->assertSame(['total' => 2], $result['summary']);
        $this->assertSame([['task' => spl_object_id($recentTask), 'today' => '2026-11-19']], $result['recent_tasks']);
        $this->assertSame([['task' => spl_object_id($overdueTask), 'today' => '2026-11-19']], $result['overdue_tasks']);
    }

    public function testListsAreOrderedNewestFirstAndEarliestDueFirst(): void
    {
        $orders = ['recent' => [], 'overdue' => []];
        $stats = $this->createMock(DashboardStatistics::class);
        $stats->method('getToday')->willReturn('2026-11-19');
        $stats->method('getStatistics')->willReturn([]);

        $make = function (string $key) use (&$orders) {
            $c = $this->createMock(Collection::class);
            $c->method('addDashboardFilter')->willReturnSelf();
            $c->method('addOverdueFilter')->willReturnSelf();
            $c->method('setPageSize')->willReturnSelf();
            $c->method('getItems')->willReturn([]);
            $c->method('setOrder')->willReturnCallback(function ($f, $d) use (&$orders, $key, $c) {
                $orders[$key][] = [$f, $d];
                return $c;
            });
            return $c;
        };
        $factory = $this->createMock(CollectionFactory::class);
        $factory->method('create')->willReturnOnConsecutiveCalls($make('recent'), $make('overdue'));

        (new DashboardService($stats, $factory, $this->createMock(TaskRowMapper::class)))
            ->getDashboard(new DashboardFilter());

        $this->assertSame([['created_at', 'DESC'], ['task_id', 'DESC']], $orders['recent']);
        $this->assertSame([['due_date', 'ASC'], ['priority', 'DESC'], ['task_id', 'ASC']], $orders['overdue']);
    }
}

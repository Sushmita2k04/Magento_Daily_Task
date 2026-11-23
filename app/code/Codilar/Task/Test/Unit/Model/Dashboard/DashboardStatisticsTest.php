<?php
declare(strict_types=1);

namespace Codilar\Task\Test\Unit\Model\Dashboard;

use Codilar\Task\Model\Dashboard\DashboardFilter;
use Codilar\Task\Model\Dashboard\DashboardStatistics;
use Codilar\Task\Model\ResourceModel\Task\Collection;
use Codilar\Task\Model\ResourceModel\Task\CollectionFactory;
use Codilar\Task\Model\Source\Priority;
use Codilar\Task\Model\Source\Status;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class DashboardStatisticsTest extends TestCase
{
    /**
     * @param Collection|null $collection
     * @return DashboardStatistics
     */
    private function statistics(?Collection $collection = null): DashboardStatistics
    {
        $factory = $this->createMock(CollectionFactory::class);
        $factory->method('create')->willReturn($collection ?? $this->createMock(Collection::class));
        $timezone = $this->createMock(TimezoneInterface::class);
        $timezone->method('date')->willReturn(new \DateTime('2026-06-15 03:00:00'));
        return new DashboardStatistics($factory, $timezone, new Status(), new Priority());
    }

    public function testSummaryAndDistributions(): void
    {
        $result = $this->statistics()->compute([
            ['status' => 0, 'priority' => 2, 'total' => 3, 'overdue' => 1],
            ['status' => 0, 'priority' => 1, 'total' => 2, 'overdue' => 0],
            ['status' => 1, 'priority' => 1, 'total' => 3, 'overdue' => 1],
            ['status' => 2, 'priority' => 0, 'total' => 2, 'overdue' => 0],
        ]);

        $this->assertSame(
            [
                'total' => 10,
                'completed' => 2,
                'pending' => 5,
                'in_progress' => 3,
                'overdue' => 2,
                'completion_percent' => 20.0,
            ],
            $result['summary']
        );
        $this->assertSame([5, 3, 2], array_column($result['status_distribution'], 'count'));
        $labels = array_column($result['status_distribution'], 'label');
        $this->assertSame(['Pending', 'In Progress', 'Completed'], $labels);
        $this->assertSame([50.0, 30.0, 20.0], array_column($result['status_distribution'], 'percent'));
        $this->assertSame([2, 5, 3], array_column($result['priority_distribution'], 'count'), 'Low, Medium, High');
        $this->assertSame(['Low', 'Medium', 'High'], array_column($result['priority_distribution'], 'label'));
    }

    public function testEmptyDataNeverDividesByZeroAndKeepsEveryBucket(): void
    {
        $result = $this->statistics()->compute([]);

        $this->assertSame(0, $result['summary']['total']);
        $this->assertSame(0.0, $result['summary']['completion_percent']);
        $this->assertCount(3, $result['status_distribution']);
        $this->assertCount(3, $result['priority_distribution']);
        $this->assertSame([0.0, 0.0, 0.0], array_column($result['status_distribution'], 'percent'));
    }

    public function testCompletionPercentIsRoundedToOneDecimal(): void
    {
        $result = $this->statistics()->compute([
            ['status' => 2, 'priority' => 1, 'total' => 1, 'overdue' => 0],
            ['status' => 0, 'priority' => 1, 'total' => 2, 'overdue' => 0],
        ]);
        $this->assertSame(33.3, $result['summary']['completion_percent']);
    }

    public function testAllCompleted(): void
    {
        $result = $this->statistics()->compute([['status' => 2, 'priority' => 1, 'total' => 4, 'overdue' => 0]]);
        $this->assertSame(100.0, $result['summary']['completion_percent']);
        $this->assertSame(0, $result['summary']['overdue']);
    }

    public function testStatisticsUseTheFilterAndStoreLocalToday(): void
    {
        $filter = new DashboardFilter();
        $collection = $this->createMock(Collection::class);
        $collection->expects($this->once())->method('addDashboardFilter')->with($filter)->willReturnSelf();
        $collection->expects($this->once())->method('getDashboardAggregates')->with('2026-06-15')->willReturn([]);

        $this->statistics($collection)->getStatistics($filter);
        $this->assertSame('2026-06-15', $this->statistics()->getToday());
    }
}

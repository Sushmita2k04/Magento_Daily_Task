<?php
declare(strict_types=1);

namespace Codilar\Task\Model\Dashboard;

use Codilar\Task\Api\Data\TaskInterface;
use Codilar\Task\Model\ResourceModel\Task\CollectionFactory;

/**
 * Builds the complete dashboard payload for a filter: numbers, distributions and the two task lists.
 * Three SQL queries in total (one grouped aggregate, one recent list, one overdue list); no COUNT queries.
 */
class DashboardService
{
    public const LIST_SIZE = 5;

    /**
     * @param DashboardStatistics $statistics
     * @param CollectionFactory $collectionFactory
     * @param TaskRowMapper $rowMapper
     */
    public function __construct(
        private readonly DashboardStatistics $statistics,
        private readonly CollectionFactory $collectionFactory,
        private readonly TaskRowMapper $rowMapper
    ) {
    }

    /**
     * Dashboard data for a filter.
     *
     * @param DashboardFilter $filter
     * @return array<string, mixed>
     */
    public function getDashboard(DashboardFilter $filter): array
    {
        $today = $this->statistics->getToday();

        return array_merge(
            ['filters' => $filter->toArray(), 'today' => $today],
            $this->statistics->getStatistics($filter),
            [
                'recent_tasks' => $this->getRecent($filter, $today),
                'overdue_tasks' => $this->getOverdue($filter, $today),
            ]
        );
    }

    /**
     * Newest tasks inside the filter.
     *
     * @param DashboardFilter $filter
     * @param string $today
     * @return array<int, array<string, mixed>>
     */
    private function getRecent(DashboardFilter $filter, string $today): array
    {
        $collection = $this->collectionFactory->create()->addDashboardFilter($filter);
        $collection->setOrder(TaskInterface::CREATED_AT, 'DESC')
            ->setOrder(TaskInterface::TASK_ID, 'DESC')
            ->setPageSize(self::LIST_SIZE);
        return $this->mapItems($collection->getItems(), $today);
    }

    /**
     * Overdue tasks inside the filter: earliest due date first, then higher priority.
     *
     * @param DashboardFilter $filter
     * @param string $today
     * @return array<int, array<string, mixed>>
     */
    private function getOverdue(DashboardFilter $filter, string $today): array
    {
        $collection = $this->collectionFactory->create()->addDashboardFilter($filter)->addOverdueFilter($today);
        $collection->setOrder(TaskInterface::DUE_DATE, 'ASC')
            ->setOrder(TaskInterface::PRIORITY, 'DESC')
            ->setOrder(TaskInterface::TASK_ID, 'ASC')
            ->setPageSize(self::LIST_SIZE);
        return $this->mapItems($collection->getItems(), $today);
    }

    /**
     * Map collection items to display rows.
     *
     * @param array $items
     * @param string $today
     * @return array<int, array<string, mixed>>
     */
    private function mapItems(array $items, string $today): array
    {
        $rows = [];
        foreach ($items as $task) {
            $rows[] = $this->rowMapper->map($task, $today);
        }
        return $rows;
    }
}

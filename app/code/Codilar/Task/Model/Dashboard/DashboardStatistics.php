<?php
declare(strict_types=1);

namespace Codilar\Task\Model\Dashboard;

use Codilar\Task\Api\Data\TaskInterface;
use Codilar\Task\Model\ResourceModel\Task\CollectionFactory;
use Codilar\Task\Model\Source\Priority;
use Codilar\Task\Model\Source\Status;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;

/**
 * Dashboard numbers: summary cards, status and priority distributions, completion percentage.
 *
 * Everything is derived from a single grouped query on the live table; no history is inferred.
 */
class DashboardStatistics
{
    /**
     * @param CollectionFactory $collectionFactory
     * @param TimezoneInterface $timezone
     * @param Status $statusSource
     * @param Priority $prioritySource
     */
    public function __construct(
        private readonly CollectionFactory $collectionFactory,
        private readonly TimezoneInterface $timezone,
        private readonly Status $statusSource,
        private readonly Priority $prioritySource
    ) {
    }

    /**
     * Store-local "today" as Y-m-d; the single definition of overdue uses it.
     *
     * @return string
     */
    public function getToday(): string
    {
        return $this->timezone->date()->format('Y-m-d');
    }

    /**
     * Compute the statistics for a filter.
     *
     * @param DashboardFilter $filter
     * @return array{
     *     summary: array<string, int|float>,
     *     status_distribution: array<int, array<string, mixed>>,
     *     priority_distribution: array<int, array<string, mixed>>
     * }
     */
    public function getStatistics(DashboardFilter $filter): array
    {
        $collection = $this->collectionFactory->create()->addDashboardFilter($filter);
        return $this->compute($collection->getDashboardAggregates($this->getToday()));
    }

    /**
     * Turn grouped rows into the dashboard structure.
     *
     * @param array $rows rows of [status, priority, total, overdue]
     * @return array{
     *     summary: array<string, int|float>,
     *     status_distribution: array<int, array<string, mixed>>,
     *     priority_distribution: array<int, array<string, mixed>>
     * }
     */
    public function compute(array $rows): array
    {
        $byStatus = [
            TaskInterface::STATUS_PENDING => 0,
            TaskInterface::STATUS_IN_PROGRESS => 0,
            TaskInterface::STATUS_COMPLETED => 0,
        ];
        $byPriority = [
            TaskInterface::PRIORITY_LOW => 0,
            TaskInterface::PRIORITY_MEDIUM => 0,
            TaskInterface::PRIORITY_HIGH => 0,
        ];
        $total = 0;
        $overdue = 0;

        foreach ($rows as $row) {
            $byStatus[$row['status']] = ($byStatus[$row['status']] ?? 0) + $row['total'];
            $byPriority[$row['priority']] = ($byPriority[$row['priority']] ?? 0) + $row['total'];
            $total += $row['total'];
            $overdue += $row['overdue'];
        }

        $completed = $byStatus[TaskInterface::STATUS_COMPLETED];
        return [
            'summary' => [
                'total' => $total,
                'completed' => $completed,
                'pending' => $byStatus[TaskInterface::STATUS_PENDING],
                'in_progress' => $byStatus[TaskInterface::STATUS_IN_PROGRESS],
                'overdue' => $overdue,
                'completion_percent' => $this->percent($completed, $total),
            ],
            'status_distribution' => $this->distribution($byStatus, $this->statusSource->getOptionArray(), $total),
            'priority_distribution' => $this->distribution(
                $byPriority,
                $this->prioritySource->getOptionArray(),
                $total
            ),
        ];
    }

    /**
     * Distribution list with every option present (zero counts included) so charts stay stable.
     *
     * @param array $counts key => count
     * @param array $labels key => label
     * @param int $total
     * @return array<int, array{key: int, label: string, count: int, percent: float}>
     */
    private function distribution(array $counts, array $labels, int $total): array
    {
        $result = [];
        foreach ($labels as $key => $label) {
            $result[] = [
                'key' => (int) $key,
                'label' => (string) $label,
                'count' => $counts[$key] ?? 0,
                'percent' => $this->percent($counts[$key] ?? 0, $total),
            ];
        }
        return $result;
    }

    /**
     * Percentage rounded to one decimal; 0 when there is nothing to divide by.
     *
     * @param int $part
     * @param int $total
     * @return float
     */
    private function percent(int $part, int $total): float
    {
        return $total > 0 ? round($part / $total * 100, 1) : 0.0;
    }
}

<?php
declare(strict_types=1);

namespace Codilar\Task\Model\ResourceModel\Task;

use Codilar\Task\Api\Data\TaskInterface;
use Codilar\Task\Model\Dashboard\DashboardFilter;
use Codilar\Task\Model\ResourceModel\Task as TaskResource;
use Codilar\Task\Model\Task;
use Magento\Framework\DB\Select;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

/**
 * Collection of tasks (used by the repository's getList and the admin grid).
 */
class Collection extends AbstractCollection
{
    /**
     * @var string
     */
    protected $_idFieldName = TaskResource::ID_FIELD;

    /**
     * @inheritDoc
     */
    protected function _construct()
    {
        $this->_init(Task::class, TaskResource::class);
    }

    /**
     * Restrict the collection to the dashboard filter (date range, statuses, priorities).
     *
     * All values are bound through addFieldToFilter(); nothing is concatenated into SQL.
     *
     * @param DashboardFilter $filter
     * @return $this
     */
    public function addDashboardFilter(DashboardFilter $filter): self
    {
        $field = $filter->getDateField() === DashboardFilter::FIELD_DUE
            ? TaskInterface::DUE_DATE
            : TaskInterface::CREATED_AT;

        if ($filter->getRangeFrom() !== null) {
            $this->addFieldToFilter($field, ['gteq' => $filter->getRangeFrom()]);
        }
        if ($filter->getRangeTo() !== null) {
            $this->addFieldToFilter($field, ['lteq' => $filter->getRangeTo()]);
        }
        if ($filter->getStatuses()) {
            $this->addFieldToFilter(TaskInterface::STATUS, ['in' => $filter->getStatuses()]);
        }
        if ($filter->getPriorities()) {
            $this->addFieldToFilter(TaskInterface::PRIORITY, ['in' => $filter->getPriorities()]);
        }
        return $this;
    }

    /**
     * Keep only overdue tasks: not Completed and due before $today (tasks without a due date never match).
     *
     * @param string $today store-local date as Y-m-d
     * @return $this
     */
    public function addOverdueFilter(string $today): self
    {
        $this->addFieldToFilter(TaskInterface::STATUS, ['neq' => TaskInterface::STATUS_COMPLETED]);
        $this->addFieldToFilter(TaskInterface::DUE_DATE, ['lt' => $today]);
        return $this;
    }

    /**
     * One grouped query for every dashboard number: tasks and overdue tasks per status and priority.
     *
     * Honours the filters already added to this collection. Returns at most 9 rows.
     *
     * @param string $today store-local date as Y-m-d
     * @return array<int, array{status: int, priority: int, total: int, overdue: int}>
     */
    public function getDashboardAggregates(string $today): array
    {
        $connection = $this->getConnection();
        $overdue = $connection->quoteInto(
            'SUM(CASE WHEN main_table.status <> ' . TaskInterface::STATUS_COMPLETED
            . ' AND main_table.due_date < ? THEN 1 ELSE 0 END)',
            $today
        );

        $select = clone $this->getSelect();
        $select->reset(Select::COLUMNS)
            ->reset(Select::ORDER)
            ->reset(Select::LIMIT_COUNT)
            ->reset(Select::LIMIT_OFFSET)
            ->columns([
                'status' => 'main_table.status',
                'priority' => 'main_table.priority',
                'total' => new \Zend_Db_Expr('COUNT(*)'),
                'overdue' => new \Zend_Db_Expr($overdue),
            ])
            ->group(['main_table.status', 'main_table.priority']);

        $rows = [];
        foreach ($connection->fetchAll($select) as $row) {
            $rows[] = [
                'status' => (int) $row['status'],
                'priority' => (int) $row['priority'],
                'total' => (int) $row['total'],
                'overdue' => (int) $row['overdue'],
            ];
        }
        return $rows;
    }
}

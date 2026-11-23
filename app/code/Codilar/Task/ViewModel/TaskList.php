<?php
declare(strict_types=1);

namespace Codilar\Task\ViewModel;

use Codilar\Task\Api\Data\TaskInterface;
use Codilar\Task\Api\TaskRepositoryInterface;
use Codilar\Task\Model\Source\Priority;
use Codilar\Task\Model\Source\Status;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SortOrderBuilder;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Locale\ResolverInterface;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;

/**
 * Presentation data for the public task list. It has no HTML and no theme dependency, so a
 * Luma template today and a Hyva template later can both render the same data.
 */
class TaskList implements ArgumentInterface
{
    public const PAGE_SIZE = 12;
    public const PAGE_PARAM = 'p';

    /**
     * @var array{items: array<int, array<string, mixed>>, total: int}|null
     */
    private ?array $result = null;

    /**
     * @param TaskRepositoryInterface $repository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param SortOrderBuilder $sortOrderBuilder
     * @param RequestInterface $request
     * @param TimezoneInterface $timezone
     * @param ResolverInterface $localeResolver
     * @param Status $statusSource
     * @param Priority $prioritySource
     * @param UrlInterface $urlBuilder
     */
    public function __construct(
        private readonly TaskRepositoryInterface $repository,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        private readonly SortOrderBuilder $sortOrderBuilder,
        private readonly RequestInterface $request,
        private readonly TimezoneInterface $timezone,
        private readonly ResolverInterface $localeResolver,
        private readonly Status $statusSource,
        private readonly Priority $prioritySource,
        private readonly UrlInterface $urlBuilder
    ) {
    }

    /**
     * Tasks on the current page, ready to display.
     *
     * Each row: id, title, description, status, status_label, priority, priority_label,
     * due_date (Y-m-d or null), due_date_formatted, is_overdue, is_completed.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getTasks(): array
    {
        return $this->load()['items'];
    }

    /**
     * Total number of tasks (all pages).
     *
     * @return int
     */
    public function getTotal(): int
    {
        return $this->load()['total'];
    }

    /**
     * Page size.
     *
     * @return int
     */
    public function getPageSize(): int
    {
        return self::PAGE_SIZE;
    }

    /**
     * Current page number, never below 1 and never past the last page.
     *
     * @return int
     */
    public function getCurrentPage(): int
    {
        $page = (int) $this->request->getParam(self::PAGE_PARAM, 1);
        return max(1, min($page, $this->getTotalPages()));
    }

    /**
     * Number of pages (at least 1).
     *
     * @return int
     */
    public function getTotalPages(): int
    {
        return max(1, (int) ceil($this->getTotal() / self::PAGE_SIZE));
    }

    /**
     * Page numbers to link to: a window around the current page (first and last are always included).
     *
     * @return int[]
     */
    public function getPageNumbers(): array
    {
        $last = $this->getTotalPages();
        $current = $this->getCurrentPage();
        $pages = array_unique(array_merge([1, $last], range(max(1, $current - 2), min($last, $current + 2))));
        sort($pages);
        return $pages;
    }

    /**
     * URL of a list page (page 1 has no query string).
     *
     * @param int $page
     * @return string
     */
    public function getPageUrl(int $page): string
    {
        $params = $page > 1 ? ['_query' => [self::PAGE_PARAM => $page]] : [];
        return $this->urlBuilder->getUrl('tasks', $params);
    }

    /**
     * Load the requested page once per request.
     *
     * Open tasks come first (Pending, In Progress, then Completed), then higher priority,
     * then the earliest due date.
     *
     * @return array{items: array<int, array<string, mixed>>, total: int}
     */
    private function load(): array
    {
        if ($this->result !== null) {
            return $this->result;
        }

        $requested = max(1, (int) $this->request->getParam(self::PAGE_PARAM, 1));
        $criteria = $this->buildCriteria($requested);
        $list = $this->repository->getList($criteria);
        $total = (int) $list->getTotalCount();

        // A page number past the end falls back to the last page.
        $lastPage = max(1, (int) ceil($total / self::PAGE_SIZE));
        if ($requested > $lastPage && $total > 0) {
            $list = $this->repository->getList($this->buildCriteria($lastPage));
        }

        $today = $this->timezone->date()->format('Y-m-d');
        $items = [];
        foreach ($list->getItems() as $task) {
            $items[] = $this->toRow($task, $today);
        }
        return $this->result = ['items' => $items, 'total' => $total];
    }

    /**
     * Search criteria for one page.
     *
     * @param int $page
     * @return \Magento\Framework\Api\SearchCriteriaInterface
     */
    private function buildCriteria(int $page)
    {
        $sorts = [];
        foreach ([
            [TaskInterface::STATUS, 'ASC'],
            [TaskInterface::PRIORITY, 'DESC'],
            [TaskInterface::DUE_DATE, 'ASC'],
            [TaskInterface::TASK_ID, 'DESC'],
        ] as [$field, $direction]) {
            $sorts[] = $this->sortOrderBuilder->setField($field)->setDirection($direction)->create();
        }

        return $this->searchCriteriaBuilder
            ->setSortOrders($sorts)
            ->setPageSize(self::PAGE_SIZE)
            ->setCurrentPage($page)
            ->create();
    }

    /**
     * Turn a task into a plain display row.
     *
     * @param TaskInterface $task
     * @param string $today store-local date as Y-m-d
     * @return array<string, mixed>
     */
    private function toRow(TaskInterface $task, string $today): array
    {
        $due = $task->getDueDate();
        $isCompleted = (int) $task->getStatus() === TaskInterface::STATUS_COMPLETED;

        return [
            'id' => (int) $task->getTaskId(),
            'title' => (string) $task->getTitle(),
            'description' => (string) $task->getDescription(),
            'status' => (int) $task->getStatus(),
            'status_label' => (string) $this->statusSource->getLabel($task->getStatus()),
            'priority' => (int) $task->getPriority(),
            'priority_label' => (string) $this->prioritySource->getLabel($task->getPriority()),
            'due_date' => $due,
            'due_date_formatted' => $due ? $this->formatDate($due) : '',
            'is_overdue' => $due !== null && !$isCompleted && $due < $today,
            'is_completed' => $isCompleted,
        ];
    }

    /**
     * Format a date-only value for display without any timezone shift.
     *
     * @param string $date Y-m-d
     * @return string
     */
    private function formatDate(string $date): string
    {
        $utc = new \DateTimeZone('UTC');
        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $date, $utc);
        if ($parsed === false) {
            return $date;
        }
        $formatter = new \IntlDateFormatter(
            $this->localeResolver->getLocale(),
            \IntlDateFormatter::MEDIUM,
            \IntlDateFormatter::NONE,
            'UTC'
        );
        return (string) $formatter->format($parsed);
    }
}

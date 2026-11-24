<?php
declare(strict_types=1);

namespace Codilar\Task\Model\Dashboard;

use Codilar\Task\Api\Data\TaskInterface;
use Codilar\Task\Model\Source\Priority;
use Codilar\Task\Model\Source\Status;
use Magento\Backend\Model\UrlInterface;
use Magento\Framework\AuthorizationInterface;
use Magento\Framework\Locale\ResolverInterface;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;

/**
 * Turns a task into the plain row shown in the dashboard tables. Values are raw text (never HTML):
 * the browser code writes them with textContent, so nothing is injected into the page.
 */
class TaskRowMapper
{
    private const DESCRIPTION_EXCERPT = 120;

    /**
     * @param Status $statusSource
     * @param Priority $prioritySource
     * @param TimezoneInterface $timezone
     * @param ResolverInterface $localeResolver
     * @param UrlInterface $urlBuilder
     * @param AuthorizationInterface $authorization
     */
    public function __construct(
        private readonly Status $statusSource,
        private readonly Priority $prioritySource,
        private readonly TimezoneInterface $timezone,
        private readonly ResolverInterface $localeResolver,
        private readonly UrlInterface $urlBuilder,
        private readonly AuthorizationInterface $authorization
    ) {
    }

    /**
     * Map one task.
     *
     * @param TaskInterface $task
     * @param string $today store-local date as Y-m-d (overdue is judged against it)
     * @return array<string, mixed>
     */
    public function map(TaskInterface $task, string $today): array
    {
        $due = $task->getDueDate();
        $isCompleted = (int) $task->getStatus() === TaskInterface::STATUS_COMPLETED;
        $description = trim((string) $task->getDescription());

        return [
            'id' => (int) $task->getTaskId(),
            'title' => (string) $task->getTitle(),
            'description' => mb_strlen($description) > self::DESCRIPTION_EXCERPT
                ? mb_substr($description, 0, self::DESCRIPTION_EXCERPT) . '…'
                : $description,
            'status' => (int) $task->getStatus(),
            'status_label' => (string) $this->statusSource->getLabel($task->getStatus()),
            'priority' => (int) $task->getPriority(),
            'priority_label' => (string) $this->prioritySource->getLabel($task->getPriority()),
            'due_date' => $due,
            'due_date_formatted' => $due ? $this->formatDate($due) : '',
            'created_at_formatted' => $this->formatCreated($task->getCreatedAt()),
            'is_overdue' => $due !== null && !$isCompleted && $due < $today,
            'edit_url' => $this->authorization->isAllowed('Codilar_Task::task_view')
                ? $this->urlBuilder->getUrl('codilar_task/task/edit', ['task_id' => (int) $task->getTaskId()])
                : null,
        ];
    }

    /**
     * Format a date-only value without any timezone shift.
     *
     * @param string $date Y-m-d
     * @return string
     */
    private function formatDate(string $date): string
    {
        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $date, new \DateTimeZone('UTC'));
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

    /**
     * Format the UTC creation timestamp in the store timezone.
     *
     * @param string|null $createdAt
     * @return string
     */
    private function formatCreated(?string $createdAt): string
    {
        if (!$createdAt) {
            return '';
        }
        return (string) $this->timezone->formatDateTime(
            new \DateTime($createdAt, new \DateTimeZone('UTC')),
            \IntlDateFormatter::MEDIUM,
            \IntlDateFormatter::SHORT
        );
    }
}

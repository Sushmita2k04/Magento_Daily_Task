<?php
declare(strict_types=1);

namespace Codilar\Task\Model;

use Codilar\Task\Api\Data\TaskInterface;
use Codilar\Task\Api\TaskRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Psr\Log\LoggerInterface;

/**
 * Deletes several tasks one by one. A failure on one task never stops or rolls back the others;
 * the caller gets a per-task report of what was deleted and what failed (and why).
 */
class TaskMassDeleter
{
    /**
     * @param TaskRepositoryInterface $repository
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly TaskRepositoryInterface $repository,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Delete the given tasks.
     *
     * @param iterable<TaskInterface> $tasks
     * @return array{deleted: array<int, string>, failed: array<int, array{title: string, reason: string}>}
     *         deleted is task_id => title; failed is task_id => [title, reason]
     */
    public function delete(iterable $tasks): array
    {
        $report = ['deleted' => [], 'failed' => []];

        foreach ($tasks as $task) {
            $id = (int) $task->getTaskId();
            $title = (string) $task->getTitle();
            try {
                $this->repository->deleteById($id);
                $report['deleted'][$id] = $title;
            } catch (NoSuchEntityException) {
                $report['failed'][$id] = ['title' => $title, 'reason' => (string) __('it no longer exists')];
            } catch (\Throwable $e) {
                // Details go to the log; the admin only sees a safe, generic reason.
                $this->logger->error($e);
                $report['failed'][$id] = ['title' => $title, 'reason' => (string) __('an error occurred, see the log')];
            }
        }

        return $report;
    }
}

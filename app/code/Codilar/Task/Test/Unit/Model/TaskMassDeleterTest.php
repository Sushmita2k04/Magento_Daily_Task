<?php
declare(strict_types=1);

namespace Codilar\Task\Test\Unit\Model;

use Codilar\Task\Api\TaskRepositoryInterface;
use Codilar\Task\Model\Task;
use Codilar\Task\Model\TaskMassDeleter;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[AllowMockObjectsWithoutExpectations]
class TaskMassDeleterTest extends TestCase
{
    /**
     * @param int $id
     * @param string $title
     * @return Task
     */
    private function task(int $id, string $title): Task
    {
        $task = (new ObjectManager($this))->getObject(Task::class);
        $task->setData(['task_id' => $id, 'title' => $title]);
        return $task;
    }

    public function testAllDeleted(): void
    {
        $repo = $this->createMock(TaskRepositoryInterface::class);
        $repo->expects($this->exactly(2))->method('deleteById');

        $report = (new TaskMassDeleter($repo, $this->createMock(LoggerInterface::class)))
            ->delete([$this->task(1, 'A'), $this->task(2, 'B')]);

        $this->assertSame([1 => 'A', 2 => 'B'], $report['deleted']);
        $this->assertSame([], $report['failed']);
    }

    public function testPartialFailureContinuesAndReportsEachTask(): void
    {
        $deleted = [];
        $repo = $this->createMock(TaskRepositoryInterface::class);
        $repo->method('deleteById')->willReturnCallback(function ($id) use (&$deleted) {
            if ($id === 2) {
                throw new NoSuchEntityException(__('gone'));
            }
            if ($id === 3) {
                throw new CouldNotDeleteException(__('SQLSTATE secret internals'));
            }
            $deleted[] = $id;
            return true;
        });
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('error'); // only the unexpected failure is logged

        $report = (new TaskMassDeleter($repo, $logger))->delete([
            $this->task(1, 'A'),
            $this->task(2, 'B'),
            $this->task(3, 'C'),
            $this->task(4, 'D'),
        ]);

        $this->assertSame([1, 4], $deleted, 'tasks after a failure are still processed');
        $this->assertSame([1 => 'A', 4 => 'D'], $report['deleted']);
        $this->assertSame([2, 3], array_keys($report['failed']));
        $this->assertSame('B', $report['failed'][2]['title']);
        $this->assertStringContainsString('no longer exists', $report['failed'][2]['reason']);
        $this->assertStringNotContainsString('SQLSTATE', $report['failed'][3]['reason'], 'DB details must not leak');
    }

    public function testAllFailed(): void
    {
        $repo = $this->createMock(TaskRepositoryInterface::class);
        $repo->method('deleteById')->willThrowException(new \RuntimeException('boom'));

        $report = (new TaskMassDeleter($repo, $this->createMock(LoggerInterface::class)))
            ->delete([$this->task(7, 'X')]);

        $this->assertSame([], $report['deleted']);
        $this->assertCount(1, $report['failed']);
    }

    public function testEmptyInput(): void
    {
        $repo = $this->createMock(TaskRepositoryInterface::class);
        $repo->expects($this->never())->method('deleteById');

        $report = (new TaskMassDeleter($repo, $this->createMock(LoggerInterface::class)))->delete([]);
        $this->assertSame(['deleted' => [], 'failed' => []], $report);
    }
}

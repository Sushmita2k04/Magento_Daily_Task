<?php
declare(strict_types=1);

namespace Codilar\Task\Test\Unit\Model;

use Codilar\Task\Api\Data\TaskInterface;
use Codilar\Task\Model\Task;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class TaskTest extends TestCase
{
    /**
     * @return Task
     */
    private function createTask(): Task
    {
        return (new ObjectManager($this))->getObject(Task::class);
    }

    /**
     * @return array<string, array{int, int}>
     */
    public static function statusProvider(): array
    {
        return [
            'pending' => [TaskInterface::STATUS_PENDING, 0],
            'in progress' => [TaskInterface::STATUS_IN_PROGRESS, 0],
            'completed' => [TaskInterface::STATUS_COMPLETED, 1],
        ];
    }

    #[DataProvider('statusProvider')]
    public function testBeforeSaveDerivesIsDoneFromStatus(int $status, int $expectedIsDone): void
    {
        $task = $this->createTask();
        $task->setTitle('x')->setStatus($status);
        $task->beforeSave();

        $this->assertSame($status, $task->getStatus());
        $this->assertSame($expectedIsDone, (int) $task->getData(TaskInterface::IS_DONE));
        $this->assertSame($expectedIsDone, $task->getIsDone());
    }

    public function testStatusOverridesStaleIsDoneOnSave(): void
    {
        $task = $this->createTask();
        $task->setTaskId(5);
        $task->setIsDone(1)->setStatus(TaskInterface::STATUS_IN_PROGRESS);
        $task->beforeSave();

        $this->assertSame(0, (int) $task->getData(TaskInterface::IS_DONE));
    }

    public function testCompletedToPendingClearsIsDone(): void
    {
        $task = $this->createTask();
        $task->setTaskId(5);
        $task->setStatus(TaskInterface::STATUS_COMPLETED);
        $task->beforeSave();
        $this->assertSame(1, $task->getIsDone());

        $task->setStatus(TaskInterface::STATUS_PENDING);
        $task->beforeSave();
        $this->assertSame(0, $task->getIsDone());
        $this->assertSame(0, (int) $task->getData(TaskInterface::IS_DONE));
    }

    public function testLegacyIsDoneOnlyOnCreateDerivesStatus(): void
    {
        $done = $this->createTask();
        $done->setTitle('x')->setIsDone(1);
        $done->beforeSave();
        $this->assertSame(TaskInterface::STATUS_COMPLETED, $done->getStatus());

        $open = $this->createTask();
        $open->setTitle('x')->setIsDone(0);
        $open->beforeSave();
        $this->assertSame(TaskInterface::STATUS_PENDING, $open->getStatus());
    }

    public function testCreateAppliesDefaults(): void
    {
        $task = $this->createTask();
        $task->setTitle('x');
        $task->beforeSave();

        $this->assertSame(TaskInterface::STATUS_PENDING, $task->getStatus());
        $this->assertSame(0, (int) $task->getData(TaskInterface::IS_DONE));
        $this->assertSame(TaskInterface::PRIORITY_MEDIUM, $task->getPriority());
    }

    public function testPartialUpdateWithoutStatusDoesNotTouchStatus(): void
    {
        $task = $this->createTask();
        $task->setTaskId(7);
        $task->setTitle('renamed');
        $task->beforeSave();

        $this->assertNull($task->getData(TaskInterface::STATUS));
        $this->assertNull($task->getData(TaskInterface::IS_DONE));
        $this->assertNull($task->getData(TaskInterface::PRIORITY));
    }

    public function testGetIsDoneIsDerivedInMemory(): void
    {
        $task = $this->createTask();
        $task->setIsDone(0)->setStatus(TaskInterface::STATUS_COMPLETED);
        $this->assertSame(1, $task->getIsDone());
    }

    public function testEmptyDueDateBecomesNull(): void
    {
        $task = $this->createTask();
        $task->setDueDate('');
        $this->assertNull($task->getDueDate());
    }
}

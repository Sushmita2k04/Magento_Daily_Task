<?php
declare(strict_types=1);

namespace Codilar\Task\Test\Unit\Model;

use Codilar\Task\Model\Task;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class TaskIdentityTest extends TestCase
{
    public function testIdentitiesIncludeListTagAndTaskTag(): void
    {
        $task = (new ObjectManager($this))->getObject(Task::class);
        $task->setId(5);
        $this->assertSame(['codilar_task', 'codilar_task_5'], array_values($task->getIdentities()));
    }

    public function testNewTaskStillHasListTag(): void
    {
        $task = (new ObjectManager($this))->getObject(Task::class);
        $this->assertSame(['codilar_task'], array_values($task->getIdentities()));
    }
}

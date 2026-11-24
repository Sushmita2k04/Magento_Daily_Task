<?php
declare(strict_types=1);

namespace Codilar\Task\Test\Unit\Controller\Adminhtml\Task;

use Codilar\Task\Controller\Adminhtml\Task\Delete;
use Codilar\Task\Controller\Adminhtml\Task\Edit;
use Codilar\Task\Controller\Adminhtml\Task\Index;
use Codilar\Task\Controller\Adminhtml\Task\MassDelete;
use Codilar\Task\Controller\Adminhtml\Task\NewAction;
use Codilar\Task\Controller\Adminhtml\Task\Save;
use Magento\Framework\App\Action\HttpPostActionInterface;
use PHPUnit\Framework\TestCase;

/**
 * The controllers' declared permissions are what keeps unauthorised admins out, so pin them down.
 */
class AclResourcesTest extends TestCase
{
    public function testEachControllerRequiresTheRightPermission(): void
    {
        $this->assertSame('Codilar_Task::task_view', Index::ADMIN_RESOURCE);
        $this->assertSame('Codilar_Task::task_view', Edit::ADMIN_RESOURCE);
        $this->assertSame('Codilar_Task::task_save', Edit::SAVE_RESOURCE);
        $this->assertSame('Codilar_Task::task_save', NewAction::ADMIN_RESOURCE);
        $this->assertSame('Codilar_Task::task_save', Save::ADMIN_RESOURCE);
        $this->assertSame('Codilar_Task::task_delete', Delete::ADMIN_RESOURCE);
        $this->assertSame('Codilar_Task::task_delete', MassDelete::ADMIN_RESOURCE);
    }

    public function testStateChangingActionsArePostOnly(): void
    {
        foreach ([Save::class, Delete::class, MassDelete::class] as $class) {
            $instance = (new \ReflectionClass($class))->newInstanceWithoutConstructor();
            $this->assertInstanceOf(HttpPostActionInterface::class, $instance);
        }
    }
}

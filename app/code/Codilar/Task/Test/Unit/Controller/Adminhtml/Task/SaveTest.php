<?php
declare(strict_types=1);

namespace Codilar\Task\Test\Unit\Controller\Adminhtml\Task;

use Codilar\Task\Api\Data\TaskInterfaceFactory;
use Codilar\Task\Api\TaskRepositoryInterface;
use Codilar\Task\Controller\Adminhtml\Task\Save;
use Codilar\Task\Model\DateInputConverter;
use Codilar\Task\Model\Task;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\View\Result\Redirect;
use Magento\Backend\Model\View\Result\RedirectFactory;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[AllowMockObjectsWithoutExpectations]
class SaveTest extends TestCase
{
    /** @var TaskRepositoryInterface&MockObject */
    private $repository;
    /** @var DataPersistorInterface&MockObject */
    private $persistor;
    /** @var ManagerInterface&MockObject */
    private $messages;
    /** @var Redirect&MockObject */
    private $redirect;
    /** @var DateInputConverter&MockObject */
    private $dateFilter;
    /** @var Http&MockObject */
    private $request;
    /** @var Task[] tasks handed to repository->save() */
    private array $saved = [];

    /**
     * @param array<string, mixed> $post
     * @param array<string, mixed> $params
     * @return Save
     */
    private function controller(array $post, array $params = []): Save
    {
        $this->request = $this->createMock(Http::class);
        $this->request->method('getPostValue')->willReturn($post);
        $this->request->method('getParam')->willReturnCallback(fn($k) => $params[$k] ?? null);

        $this->messages = $this->createMock(ManagerInterface::class);
        $this->redirect = $this->createMock(Redirect::class);
        $this->redirect->method('setPath')->willReturnSelf();
        $redirectFactory = $this->createMock(RedirectFactory::class);
        $redirectFactory->method('create')->willReturn($this->redirect);

        $context = $this->createMock(Context::class);
        $context->method('getRequest')->willReturn($this->request);
        $context->method('getMessageManager')->willReturn($this->messages);
        $context->method('getResultRedirectFactory')->willReturn($redirectFactory);

        $this->repository = $this->createMock(TaskRepositoryInterface::class);
        $this->repository->method('save')->willReturnCallback(function (Task $task) {
            $this->saved[] = $task;
            return $task->setTaskId($task->getTaskId() ?: 77);
        });
        $factory = $this->createMock(TaskInterfaceFactory::class);
        $factory->method('create')->willReturnCallback(fn() => (new ObjectManager($this))->getObject(Task::class));
        $this->persistor = $this->createMock(DataPersistorInterface::class);
        $this->dateFilter = $this->createMock(DateInputConverter::class);

        return new Save(
            $context,
            $this->repository,
            $factory,
            $this->persistor,
            $this->dateFilter,
            $this->createMock(LoggerInterface::class)
        );
    }

    public function testSuccessSavesAndReturnsToGrid(): void
    {
        $controller = $this->controller(
            ['title' => 'T', 'description' => '', 'status' => '1', 'priority' => '2', 'due_date' => '']
        );
        $this->persistor->expects($this->once())->method('clear');
        $this->messages->expects($this->once())->method('addSuccessMessage');
        $this->redirect->expects($this->once())->method('setPath')->with('*/*/');

        $controller->execute();

        $task = $this->saved[0];
        $this->assertNull($task->getDescription(), 'empty description is stored as null');
        $this->assertNull($task->getDueDate());
        $this->assertNull($task->getData('is_done'), 'is_done is never taken from the form');
    }

    public function testPostedIsDoneIsIgnored(): void
    {
        $this->controller(['title' => 'T', 'status' => '0', 'is_done' => '1'])->execute();
        $this->assertNull($this->saved[0]->getData('is_done'));
    }

    public function testLocaleDateIsConvertedToIso(): void
    {
        $controller = $this->controller(['title' => 'T', 'due_date' => '12/31/26']);
        $this->dateFilter->expects($this->once())->method('toIsoDate')->with('12/31/26')->willReturn('2026-12-31');
        $controller->execute();
        $this->assertSame('2026-12-31', $this->saved[0]->getDueDate());
    }

    public function testUnparseableDateIsPassedOnSoValidationReportsIt(): void
    {
        $controller = $this->controller(['title' => 'T', 'due_date' => 'not a date']);
        $this->dateFilter->method('toIsoDate')->willReturn(null);
        $controller->execute();
        $this->assertSame('not a date', $this->saved[0]->getDueDate());
    }

    public function testSaveAndContinueRedirectsToEdit(): void
    {
        $controller = $this->controller(['task_id' => '5', 'title' => 'T'], ['back' => 'edit']);
        $this->redirect->expects($this->once())->method('setPath')->with('*/*/edit', ['task_id' => 5]);
        $controller->execute();
    }

    public function testNewTaskValidationErrorKeepsDataAndReturnsToNewForm(): void
    {
        $controller = $this->controller(['title' => ' ']);
        $e = new InputException(__('Invalid'));
        $e->addError(__('first problem'));
        $e->addError(__('second problem'));
        $this->repository->method('save')->willThrowException($e);

        $this->messages->expects($this->exactly(2))->method('addErrorMessage');
        $this->persistor->expects($this->once())->method('set')->with('codilar_task', ['title' => ' ']);
        $this->redirect->expects($this->once())->method('setPath')->with('*/*/new');

        $controller->execute();
    }

    public function testExistingTaskValidationErrorReturnsToItsEditForm(): void
    {
        $controller = $this->controller(['task_id' => '9', 'title' => ' ']);
        $this->repository->method('save')->willThrowException(new InputException(__('bad')));
        $this->redirect->expects($this->once())->method('setPath')->with('*/*/edit', ['task_id' => 9]);
        $controller->execute();
    }

    public function testDeletedTaskGoesBackToGrid(): void
    {
        $controller = $this->controller(['task_id' => '9', 'title' => 'T']);
        $this->repository->method('save')->willThrowException(new NoSuchEntityException(__('gone')));
        $this->messages->expects($this->once())->method('addErrorMessage');
        $this->redirect->expects($this->once())->method('setPath')->with('*/*/');
        $controller->execute();
    }

    public function testEmptyPostDoesNothing(): void
    {
        $controller = $this->controller([]);
        $this->repository->expects($this->never())->method('save');
        $controller->execute();
        $this->assertSame([], $this->saved);
    }
}

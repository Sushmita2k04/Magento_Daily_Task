<?php
declare(strict_types=1);

namespace Codilar\Task\Test\Unit\Block;

use Codilar\Task\Block\Adminhtml\Task\AddButton;
use Codilar\Task\Block\Adminhtml\Task\Edit\DeleteButton;
use Codilar\Task\Block\Adminhtml\Task\Edit\SaveButton;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\AuthorizationInterface;
use Magento\Framework\UrlInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class ButtonsTest extends TestCase
{
    /**
     * @param string[] $allowed ACL resources the user holds
     * @return AuthorizationInterface
     */
    private function auth(array $allowed): AuthorizationInterface
    {
        $auth = $this->createMock(AuthorizationInterface::class);
        $auth->method('isAllowed')->willReturnCallback(fn($r) => in_array($r, $allowed, true));
        return $auth;
    }

    /**
     * @param int $taskId
     * @return RequestInterface
     */
    private function request(int $taskId): RequestInterface
    {
        $request = $this->createMock(RequestInterface::class);
        $request->method('getParam')->willReturnCallback(fn($k) => $k === 'task_id' ? $taskId : null);
        return $request;
    }

    public function testSaveButtonOnlyWithSavePermission(): void
    {
        $url = $this->createMock(UrlInterface::class);
        $viewOnly = new SaveButton($url, $this->request(1), $this->auth(['Codilar_Task::task_view']));
        $canSave = new SaveButton($url, $this->request(1), $this->auth(['Codilar_Task::task_save']));

        $this->assertSame([], $viewOnly->getButtonData());
        $this->assertNotEmpty($canSave->getButtonData());
    }

    public function testDeleteButtonNeedsExistingTaskAndDeletePermission(): void
    {
        $url = $this->createMock(UrlInterface::class);
        $url->method('getUrl')->willReturn('http://x/delete');
        $canDelete = $this->auth(['Codilar_Task::task_delete']);

        $newTask = new DeleteButton($url, $this->request(0), $canDelete);
        $noPermission = new DeleteButton($url, $this->request(5), $this->auth(['Codilar_Task::task_save']));
        $allowed = new DeleteButton($url, $this->request(5), $canDelete);

        $this->assertSame([], $newTask->getButtonData(), 'new task');
        $this->assertSame([], $noPermission->getButtonData(), 'no delete permission');
        $this->assertStringContainsString('deleteConfirm', $allowed->getButtonData()['on_click']);
    }

    public function testAddButtonOnlyWithSavePermission(): void
    {
        $url = $this->createMock(UrlInterface::class);
        $this->assertSame([], (new AddButton($url, $this->auth([])))->getButtonData());
        $this->assertNotEmpty((new AddButton($url, $this->auth(['Codilar_Task::task_save'])))->getButtonData());
    }
}

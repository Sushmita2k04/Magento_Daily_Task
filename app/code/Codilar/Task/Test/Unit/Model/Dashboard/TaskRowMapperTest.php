<?php
declare(strict_types=1);

namespace Codilar\Task\Test\Unit\Model\Dashboard;

use Codilar\Task\Model\Dashboard\TaskRowMapper;
use Codilar\Task\Model\Source\Priority;
use Codilar\Task\Model\Source\Status;
use Codilar\Task\Model\Task;
use Magento\Backend\Model\UrlInterface;
use Magento\Framework\AuthorizationInterface;
use Magento\Framework\Locale\ResolverInterface;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class TaskRowMapperTest extends TestCase
{
    /**
     * @param bool $canView
     * @return TaskRowMapper
     */
    private function mapper(bool $canView = true): TaskRowMapper
    {
        $timezone = $this->createMock(TimezoneInterface::class);
        $timezone->method('formatDateTime')->willReturnCallback(
            fn(\DateTimeInterface $d) => $d->setTimezone(new \DateTimeZone('Asia/Kolkata'))->format('M j, Y, g:i A')
        );
        $locale = $this->createMock(ResolverInterface::class);
        $locale->method('getLocale')->willReturn('en_US');
        $url = $this->createMock(UrlInterface::class);
        $url->method('getUrl')->willReturnCallback(fn($route, $p) => 'http://admin/' . $route . '/id/' . $p['task_id']);
        $auth = $this->createMock(AuthorizationInterface::class);
        $auth->method('isAllowed')->willReturn($canView);
        return new TaskRowMapper(new Status(), new Priority(), $timezone, $locale, $url, $auth);
    }

    /**
     * @param array<string, mixed> $data
     * @return Task
     */
    private function task(array $data): Task
    {
        $task = (new ObjectManager($this))->getObject(Task::class);
        $task->setData($data + ['task_id' => 3, 'title' => 'T', 'status' => 0, 'priority' => 1]);
        return $task;
    }

    public function testMapsDisplayFields(): void
    {
        $row = $this->mapper()->map($this->task([
            'title' => 'Fix <b>bug</b>', 'status' => 1, 'priority' => 2, 'due_date' => '2026-12-31',
            'created_at' => '2026-11-19 18:30:00', 'description' => 'details',
        ]), '2026-11-19');

        $this->assertSame(3, $row['id']);
        $this->assertSame('Fix <b>bug</b>', $row['title'], 'raw text; the browser code writes it with textContent');
        $this->assertSame('In Progress', $row['status_label']);
        $this->assertSame('High', $row['priority_label']);
        $this->assertSame('Dec 31, 2026', $row['due_date_formatted']);
        $this->assertSame('Nov 20, 2026, 12:00 AM', $row['created_at_formatted'], 'UTC shown in store time');
        $this->assertSame('http://admin/codilar_task/task/edit/id/3', $row['edit_url']);
        $this->assertFalse($row['is_overdue']);
    }

    public function testOverdueRules(): void
    {
        $m = $this->mapper();
        $this->assertTrue($m->map($this->task(['due_date' => '2026-11-18']), '2026-11-19')['is_overdue']);
        $this->assertFalse($m->map($this->task(['due_date' => '2026-11-19']), '2026-11-19')['is_overdue']);
        $completed = $m->map($this->task(['due_date' => '2026-11-18', 'status' => 2]), '2026-11-19');
        $this->assertFalse($completed['is_overdue']);
        $this->assertFalse($m->map($this->task([]), '2026-11-19')['is_overdue']);
    }

    public function testMissingDueDateAndCreatedAreEmptyStrings(): void
    {
        $row = $this->mapper()->map($this->task([]), '2026-11-19');
        $this->assertNull($row['due_date']);
        $this->assertSame('', $row['due_date_formatted']);
        $this->assertSame('', $row['created_at_formatted']);
    }

    public function testLongDescriptionIsShortenedByCharacters(): void
    {
        $row = $this->mapper()->map($this->task(['description' => str_repeat('é', 300)]), '2026-11-19');
        $this->assertSame(121, mb_strlen($row['description']));
        $this->assertStringEndsWith('…', $row['description']);
    }

    public function testNoEditLinkWithoutViewPermission(): void
    {
        $this->assertNull($this->mapper(false)->map($this->task([]), '2026-11-19')['edit_url']);
    }
}

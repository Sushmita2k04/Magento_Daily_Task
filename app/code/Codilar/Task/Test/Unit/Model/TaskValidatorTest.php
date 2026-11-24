<?php
declare(strict_types=1);

namespace Codilar\Task\Test\Unit\Model;

use Codilar\Task\Model\Source\Priority;
use Codilar\Task\Model\Source\Status;
use Codilar\Task\Model\Task;
use Codilar\Task\Model\TaskValidator;
use Magento\Framework\Exception\InputException;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class TaskValidatorTest extends TestCase
{
    /**
     * @var TaskValidator
     */
    private TaskValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new TaskValidator(new Status(), new Priority());
    }

    /**
     * @param array<string, mixed> $data
     * @return Task
     */
    private function task(array $data): Task
    {
        $task = (new ObjectManager($this))->getObject(Task::class);
        $task->setData($data);
        return $task;
    }

    public function testValidCompleteTaskPasses(): void
    {
        $this->validator->validate($this->task([
            'title' => 'Write docs',
            'description' => 'Body',
            'status' => 1,
            'priority' => 2,
            'due_date' => '2026-12-31',
        ]));
        $this->addToAssertionCount(1);
    }

    public function testMinimalLegacyPayloadPasses(): void
    {
        $this->validator->validate($this->task(['title' => 'Legacy', 'is_done' => 1]));
        $this->addToAssertionCount(1);
    }

    public function testPartialUpdateDoesNotRequireTitle(): void
    {
        $this->validator->validate($this->task(['status' => 2]), false);
        $this->addToAssertionCount(1);
    }

    /**
     * @return array<string, array{array<string, mixed>, bool}>
     */
    public static function invalidProvider(): array
    {
        return [
            'missing title' => [['status' => 0], true],
            'blank title' => [['title' => '   '], true],
            'blank title on partial update' => [['title' => ''], false],
            'title too long' => [['title' => str_repeat('a', 256)], true],
            'status out of range' => [['title' => 'x', 'status' => 3], true],
            'status not numeric' => [['title' => 'x', 'status' => 'done'], true],
            'status fractional' => [['title' => 'x', 'status' => '1.5'], true],
            'priority out of range' => [['title' => 'x', 'priority' => 5], true],
            'is_done invalid' => [['title' => 'x', 'is_done' => 2], true],
            'conflict pending vs done' => [['title' => 'x', 'status' => 0, 'is_done' => 1], true],
            'conflict completed vs not done' => [['title' => 'x', 'status' => 2, 'is_done' => 0], true],
            'conflict in progress vs done' => [['title' => 'x', 'status' => 1, 'is_done' => 1], true],
            'bad date format' => [['title' => 'x', 'due_date' => '31/12/2026'], true],
            'impossible date' => [['title' => 'x', 'due_date' => '2026-02-30'], true],
            'description too long' => [['title' => 'x', 'description' => str_repeat('a', 65536)], true],
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    #[DataProvider('invalidProvider')]
    public function testInvalidDataIsRejected(array $data, bool $requireTitle): void
    {
        $this->expectException(InputException::class);
        $this->validator->validate($this->task($data), $requireTitle);
    }

    public function testConsistentStatusAndIsDoneAreAccepted(): void
    {
        $this->validator->validate($this->task(['title' => 'x', 'status' => 2, 'is_done' => 1]));
        $this->validator->validate($this->task(['title' => 'x', 'status' => 1, 'is_done' => 0]));
        $this->addToAssertionCount(2);
    }

    public function testAllProblemsAreReportedTogether(): void
    {
        try {
            $this->validator->validate($this->task(['title' => '', 'status' => 9, 'priority' => 9]));
            $this->fail('Expected InputException');
        } catch (InputException $e) {
            $this->assertCount(3, $e->getErrors());
        }
    }
}

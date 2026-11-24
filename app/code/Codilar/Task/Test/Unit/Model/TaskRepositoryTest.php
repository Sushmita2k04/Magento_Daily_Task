<?php
declare(strict_types=1);

namespace Codilar\Task\Test\Unit\Model;

use Codilar\Task\Api\Data\TaskInterface;
use Codilar\Task\Api\Data\TaskInterfaceFactory;
use Codilar\Task\Api\Data\TaskSearchResultsInterface;
use Codilar\Task\Api\Data\TaskSearchResultsInterfaceFactory;
use Codilar\Task\Model\ResourceModel\Task as TaskResource;
use Codilar\Task\Model\ResourceModel\Task\Collection;
use Codilar\Task\Model\ResourceModel\Task\CollectionFactory;
use Codilar\Task\Model\Source\Priority;
use Codilar\Task\Model\Source\Status;
use Codilar\Task\Model\Task;
use Codilar\Task\Model\TaskRepository;
use Codilar\Task\Model\TaskValidator;
use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class TaskRepositoryTest extends TestCase
{
    /**
     * @var ObjectManager
     */
    private ObjectManager $objects;

    /**
     * @var TaskResource&MockObject
     */
    private $resource;

    /**
     * @var array<int, array<string, mixed>> fake table rows keyed by task_id
     */
    private array $rows = [];

    /**
     * @var array<int, array<string, mixed>> every payload passed to resource->save()
     */
    private array $saved = [];

    /**
     * @var TaskRepository
     */
    private TaskRepository $repository;

    /**
     * @var CollectionProcessorInterface&MockObject
     */
    private $processor;

    /**
     * @var Collection&MockObject
     */
    private $collection;

    /**
     * @var TaskSearchResultsInterface&MockObject
     */
    private $searchResults;

    protected function setUp(): void
    {
        $this->objects = new ObjectManager($this);
        $this->resource = $this->createMock(TaskResource::class);

        $this->resource->method('load')->willReturnCallback(
            function (Task $task, $id) {
                if (isset($this->rows[$id])) {
                    $task->setData($this->rows[$id]);
                }
                return $this->resource;
            }
        );
        $this->resource->method('save')->willReturnCallback(
            function (Task $task) {
                $task->beforeSave(); // the real resource model does this
                $data = $task->getData();
                $this->saved[] = $data;
                $id = $data['task_id'] ?? (count($this->rows) + 1);
                $this->rows[$id] = array_merge(
                    $this->rows[$id] ?? ['created_at' => '2026-01-01 00:00:00'],
                    $data,
                    ['task_id' => $id, 'updated_at' => '2026-02-02 00:00:00']
                );
                $task->setData('task_id', $id);
                return $this->resource;
            }
        );
        $this->resource->method('delete')->willReturnCallback(
            function (Task $task) {
                unset($this->rows[$task->getTaskId()]);
                return $this->resource;
            }
        );

        $taskFactory = $this->createMock(TaskInterfaceFactory::class);
        $taskFactory->method('create')->willReturnCallback(fn() => $this->newTask());

        $this->collection = $this->createMock(Collection::class);
        $collectionFactory = $this->createMock(CollectionFactory::class);
        $collectionFactory->method('create')->willReturn($this->collection);

        $this->searchResults = $this->createMock(TaskSearchResultsInterface::class);
        $searchResultsFactory = $this->createMock(TaskSearchResultsInterfaceFactory::class);
        $searchResultsFactory->method('create')->willReturn($this->searchResults);

        $this->processor = $this->createMock(CollectionProcessorInterface::class);

        $this->repository = new TaskRepository(
            $this->resource,
            $taskFactory,
            new TaskValidator(new Status(), new Priority()),
            $collectionFactory,
            $searchResultsFactory,
            $this->processor
        );
    }

    /**
     * @param array<string, mixed> $data
     * @return Task
     */
    private function newTask(array $data = []): Task
    {
        $task = $this->objects->getObject(Task::class);
        $task->setData($data);
        return $task;
    }

    /**
     * @param array<string, mixed> $row
     * @return int
     */
    private function seed(array $row = []): int
    {
        $id = count($this->rows) + 1;
        $this->rows[$id] = array_merge([
            'task_id' => $id,
            'title' => 'Existing',
            'description' => 'Keep me',
            'status' => 1,
            'priority' => 2,
            'due_date' => '2026-12-01',
            'is_done' => 0,
            'created_at' => '2026-01-01 00:00:00',
            'updated_at' => '2026-01-01 00:00:00',
        ], $row);
        return $id;
    }

    public function testCreateAppliesDefaultsAndIgnoresServerFields(): void
    {
        $result = $this->repository->save($this->newTask([
            'task_id' => null,
            'title' => '  New task ',
            'created_at' => '1999-01-01 00:00:00',
            'updated_at' => '1999-01-01 00:00:00',
        ]));

        $payload = $this->saved[0];
        $this->assertSame('New task', $payload['title']);
        $this->assertArrayNotHasKey('created_at', $payload);
        $this->assertArrayNotHasKey('updated_at', $payload);
        $this->assertSame(TaskInterface::STATUS_PENDING, $result->getStatus());
        $this->assertSame(TaskInterface::PRIORITY_MEDIUM, $result->getPriority());
        $this->assertSame(0, $result->getIsDone());
    }

    public function testCreateWithLegacyIsDoneOnlyIsCompleted(): void
    {
        $result = $this->repository->save($this->newTask(['title' => 'Legacy', 'is_done' => 1]));

        $this->assertSame(TaskInterface::STATUS_COMPLETED, $result->getStatus());
        $this->assertSame(1, $result->getIsDone());
    }

    public function testCreateWithoutTitleIsRejected(): void
    {
        $this->expectException(InputException::class);
        $this->repository->save($this->newTask(['status' => 1]));
    }

    public function testConflictingStatusAndIsDoneIsRejectedAndNothingIsWritten(): void
    {
        try {
            $this->repository->save($this->newTask(['title' => 'x', 'status' => 0, 'is_done' => 1]));
            $this->fail('Expected InputException');
        } catch (InputException $e) {
            $this->assertSame([], $this->saved);
        }
    }

    public function testUpdateChangesOnlySuppliedFields(): void
    {
        $id = $this->seed();
        $result = $this->repository->save($this->newTask(['task_id' => $id, 'title' => 'Renamed']));

        $this->assertSame('Renamed', $result->getTitle());
        $this->assertSame('Keep me', $result->getDescription());
        $this->assertSame(TaskInterface::STATUS_IN_PROGRESS, $result->getStatus());
        $this->assertSame(TaskInterface::PRIORITY_HIGH, $result->getPriority());
        $this->assertSame('2026-12-01', $result->getDueDate());
    }

    public function testUpdateStatusKeepsIsDoneInSync(): void
    {
        $id = $this->seed();
        $result = $this->repository->save($this->newTask(['task_id' => $id, 'status' => 2]));
        $this->assertSame(1, $result->getIsDone());
        $this->assertSame(1, (int) $this->rows[$id]['is_done']);

        $result = $this->repository->save($this->newTask(['task_id' => $id, 'status' => 0]));
        $this->assertSame(0, $result->getIsDone());
        $this->assertSame(0, (int) $this->rows[$id]['is_done']);
    }

    public function testLegacyIsDoneOnUpdateMapsOntoStatus(): void
    {
        $id = $this->seed(['status' => 1]);

        $done = $this->repository->save($this->newTask(['task_id' => $id, 'is_done' => 1]));
        $this->assertSame(TaskInterface::STATUS_COMPLETED, $done->getStatus());

        $reopened = $this->repository->save($this->newTask(['task_id' => $id, 'is_done' => 0]));
        $this->assertSame(TaskInterface::STATUS_PENDING, $reopened->getStatus());
    }

    public function testLegacyIsDoneZeroDoesNotResetInProgress(): void
    {
        $id = $this->seed(['status' => 1]);
        $result = $this->repository->save($this->newTask(['task_id' => $id, 'is_done' => 0]));

        $this->assertSame(TaskInterface::STATUS_IN_PROGRESS, $result->getStatus());
    }

    public function testUpdateCanClearDescriptionAndDueDateButNotTitle(): void
    {
        $id = $this->seed();
        $result = $this->repository->save($this->newTask([
            'task_id' => $id,
            'title' => 'Still here',
            'description' => null,
            'due_date' => null,
        ]));

        $this->assertNull($result->getDescription());
        $this->assertNull($result->getDueDate());
        $this->assertSame('Still here', $result->getTitle());
    }

    public function testUpdateNeverWritesTimestampsBack(): void
    {
        $id = $this->seed();
        $this->repository->save($this->newTask([
            'task_id' => $id,
            'title' => 'Renamed',
            'created_at' => '1999-01-01 00:00:00',
            'updated_at' => '1999-01-01 00:00:00',
        ]));

        $this->assertArrayNotHasKey('updated_at', $this->saved[0]);
        $this->assertArrayNotHasKey('created_at', $this->saved[0]);
    }

    public function testUpdateUsesUrlIdAndChangesOnlySuppliedFields(): void
    {
        $id = $this->seed();
        $result = $this->repository->update($id, $this->newTask(['priority' => 0]));

        $this->assertSame($id, $result->getTaskId());
        $this->assertSame(TaskInterface::PRIORITY_LOW, $result->getPriority());
        $this->assertSame('Existing', $result->getTitle());
    }

    public function testUpdateRejectsMismatchedBodyId(): void
    {
        $id = $this->seed();
        try {
            $this->repository->update($id, $this->newTask(['task_id' => $id + 5, 'title' => 'Hijack']));
            $this->fail('Expected InputException');
        } catch (InputException $e) {
            $this->assertSame([], $this->saved);
        }
    }

    public function testUpdateAcceptsMatchingBodyId(): void
    {
        $id = $this->seed();
        $result = $this->repository->update($id, $this->newTask(['task_id' => (string) $id, 'title' => 'Same id']));
        $this->assertSame('Same id', $result->getTitle());
    }

    public function testUpdateOfMissingTaskThrowsAndDoesNotInsert(): void
    {
        try {
            $this->repository->save($this->newTask(['task_id' => 999, 'title' => 'Ghost']));
            $this->fail('Expected NoSuchEntityException');
        } catch (NoSuchEntityException $e) {
            $this->assertSame([], $this->saved);
        }
    }

    public function testSaveFailureIsWrapped(): void
    {
        $resource = $this->createMock(TaskResource::class);
        $resource->method('save')->willThrowException(new \RuntimeException('db down'));
        $repository = new TaskRepository(
            $resource,
            $this->createMock(TaskInterfaceFactory::class),
            new TaskValidator(new Status(), new Priority()),
            $this->createMock(CollectionFactory::class),
            $this->createMock(TaskSearchResultsInterfaceFactory::class),
            $this->processor
        );

        $this->expectException(CouldNotSaveException::class);
        $repository->save($this->newTask(['title' => 'x']));
    }

    public function testGetByIdThrowsForUnknownId(): void
    {
        $this->expectException(NoSuchEntityException::class);
        $this->repository->getById(42);
    }

    public function testDeleteById(): void
    {
        $id = $this->seed();
        $this->assertTrue($this->repository->deleteById($id));
        $this->assertArrayNotHasKey($id, $this->rows);
    }

    public function testDeleteUnknownIdThrows(): void
    {
        $this->expectException(NoSuchEntityException::class);
        $this->repository->deleteById(42);
    }

    public function testDeleteFailureIsWrapped(): void
    {
        $this->rows[1] = ['task_id' => 1, 'title' => 'x'];
        $resource = $this->createMock(TaskResource::class);
        $resource->method('load')->willReturnCallback(
            function (Task $task, $id) use ($resource) {
                $task->setData($this->rows[$id]);
                return $resource;
            }
        );
        $resource->method('delete')->willThrowException(new \RuntimeException('locked'));
        $factory = $this->createMock(TaskInterfaceFactory::class);
        $factory->method('create')->willReturnCallback(fn() => $this->newTask());
        $repository = new TaskRepository(
            $resource,
            $factory,
            new TaskValidator(new Status(), new Priority()),
            $this->createMock(CollectionFactory::class),
            $this->createMock(TaskSearchResultsInterfaceFactory::class),
            $this->processor
        );

        $this->expectException(CouldNotDeleteException::class);
        $repository->deleteById(1);
    }

    public function testGetListUsesCollectionProcessorAndReturnsTotal(): void
    {
        $criteria = $this->createMock(SearchCriteriaInterface::class);
        $items = [5 => $this->newTask(['task_id' => 5]), 9 => $this->newTask(['task_id' => 9])];
        $this->collection->method('getItems')->willReturn($items);
        $this->collection->method('getSize')->willReturn(17);

        $this->processor->expects($this->once())->method('process')->with($criteria, $this->collection);

        $this->searchResults->expects($this->once())->method('setSearchCriteria')->with($criteria);
        $this->searchResults->expects($this->once())->method('setItems')->with(array_values($items));
        $this->searchResults->expects($this->once())->method('setTotalCount')->with(17);

        $results = $this->repository->getList($criteria);
        $this->assertSame($this->searchResults, $results);
    }

    /**
     * @return array<string, array{int|null, int}>
     */
    public static function pageSizeProvider(): array
    {
        return [
            'none requested' => [null, TaskRepository::MAX_PAGE_SIZE],
            'zero' => [0, TaskRepository::MAX_PAGE_SIZE],
            'huge' => [1000000, TaskRepository::MAX_PAGE_SIZE],
            'just over' => [TaskRepository::MAX_PAGE_SIZE + 1, TaskRepository::MAX_PAGE_SIZE],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('pageSizeProvider')]
    public function testGetListCapsPageSize(?int $requested, int $expected): void
    {
        $criteria = $this->createMock(SearchCriteriaInterface::class);
        $criteria->method('getPageSize')->willReturn($requested);
        $criteria->expects($this->once())->method('setPageSize')->with($expected);
        $this->collection->method('getItems')->willReturn([]);
        $this->repository->getList($criteria);
    }

    public function testGetListKeepsReasonablePageSize(): void
    {
        $criteria = $this->createMock(SearchCriteriaInterface::class);
        $criteria->method('getPageSize')->willReturn(50);
        $criteria->expects($this->never())->method('setPageSize');
        $this->collection->method('getItems')->willReturn([]);
        $this->repository->getList($criteria);
    }

    public function testGetListRejectsUnknownFilterAndSortFields(): void
    {
        $filter = $this->createMock(\Magento\Framework\Api\Filter::class);
        $filter->method('getField')->willReturn('no_such_column');
        $group = $this->createMock(\Magento\Framework\Api\Search\FilterGroup::class);
        $group->method('getFilters')->willReturn([$filter]);
        $sort = $this->createMock(\Magento\Framework\Api\SortOrder::class);
        $sort->method('getField')->willReturn('also; DROP TABLE x');
        $criteria = $this->createMock(SearchCriteriaInterface::class);
        $criteria->method('getFilterGroups')->willReturn([$group]);
        $criteria->method('getSortOrders')->willReturn([$sort]);

        $this->processor->expects($this->never())->method('process');
        try {
            $this->repository->getList($criteria);
            $this->fail('Expected InputException');
        } catch (InputException $e) {
            $this->assertStringContainsString('no_such_column', $e->getMessage());
            $this->assertStringContainsString('also; DROP TABLE x', $e->getMessage());
        }
    }

    public function testGetListAcceptsAllTaskColumns(): void
    {
        $filter = $this->createMock(\Magento\Framework\Api\Filter::class);
        $filter->method('getField')->willReturn('due_date');
        $group = $this->createMock(\Magento\Framework\Api\Search\FilterGroup::class);
        $group->method('getFilters')->willReturn([$filter]);
        $sort = $this->createMock(\Magento\Framework\Api\SortOrder::class);
        $sort->method('getField')->willReturn('priority');
        $criteria = $this->createMock(SearchCriteriaInterface::class);
        $criteria->method('getFilterGroups')->willReturn([$group]);
        $criteria->method('getSortOrders')->willReturn([$sort]);
        $criteria->method('getPageSize')->willReturn(10);
        $this->collection->method('getItems')->willReturn([]);

        $this->processor->expects($this->once())->method('process');
        $this->repository->getList($criteria);
    }
}

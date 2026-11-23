<?php
declare(strict_types=1);

namespace Codilar\Task\Test\Unit\ViewModel;

use Codilar\Task\Api\Data\TaskSearchResultsInterface;
use Codilar\Task\Api\TaskRepositoryInterface;
use Codilar\Task\Model\Source\Priority;
use Codilar\Task\Model\Source\Status;
use Codilar\Task\Model\Task;
use Codilar\Task\ViewModel\TaskList;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Api\SortOrder;
use Magento\Framework\Api\SortOrderBuilder;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Locale\ResolverInterface;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use Magento\Framework\UrlInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class TaskListTest extends TestCase
{
    /** @var TaskRepositoryInterface&MockObject */
    private $repository;
    /** @var SearchCriteriaBuilder&MockObject */
    private $criteriaBuilder;
    /** @var array<int, array{string, string}> sort orders requested, in order */
    private array $sorts = [];
    /** @var int[] pages requested from the repository */
    private array $pagesRequested = [];
    /** @var int|null */
    private ?int $pageSize = null;
    /** @var int */
    private int $currentPage = 1;

    /**
     * @param mixed $pageParam value of the ?p= request parameter
     * @param Task[] $items
     * @param int $total
     * @param string $today store-local date
     * @return TaskList
     */
    private function viewModel($pageParam, array $items, int $total, string $today = '2026-06-15'): TaskList
    {
        $this->sorts = [];
        $this->pagesRequested = [];

        $sortBuilder = $this->createMock(SortOrderBuilder::class);
        $field = '';
        $sortBuilder->method('setField')->willReturnCallback(function ($f) use ($sortBuilder, &$field) {
            $field = $f;
            return $sortBuilder;
        });
        $sortBuilder->method('setDirection')->willReturnCallback(function ($d) use ($sortBuilder, &$field) {
            $this->sorts[] = [$field, $d];
            return $sortBuilder;
        });
        $sortBuilder->method('create')->willReturn($this->createMock(SortOrder::class));

        $this->criteriaBuilder = $this->createMock(SearchCriteriaBuilder::class);
        $this->criteriaBuilder->method('setSortOrders')->willReturnSelf();
        $this->criteriaBuilder->method('setPageSize')->willReturnCallback(function ($n) {
            $this->pageSize = $n;
            return $this->criteriaBuilder;
        });
        $this->criteriaBuilder->method('setCurrentPage')->willReturnCallback(function ($n) {
            $this->currentPage = $n;
            return $this->criteriaBuilder;
        });
        $this->criteriaBuilder->method('create')->willReturnCallback(function () {
            $criteria = $this->createMock(SearchCriteriaInterface::class);
            $this->pagesRequested[] = $this->currentPage;
            return $criteria;
        });

        $results = $this->createMock(TaskSearchResultsInterface::class);
        $results->method('getItems')->willReturn($items);
        $results->method('getTotalCount')->willReturn($total);
        $this->repository = $this->createMock(TaskRepositoryInterface::class);
        $this->repository->method('getList')->willReturn($results);

        $request = $this->createMock(RequestInterface::class);
        $request->method('getParam')->willReturnCallback(fn($k, $d = null) => $k === 'p' ? $pageParam : $d);

        $timezone = $this->createMock(TimezoneInterface::class);
        $timezone->method('date')->willReturn(new \DateTime($today));
        $locale = $this->createMock(ResolverInterface::class);
        $locale->method('getLocale')->willReturn('en_US');
        $url = $this->createMock(UrlInterface::class);
        $url->method('getUrl')->willReturnCallback(
            fn($route, $params = []) => 'http://x/' . $route
                . (isset($params['_query']['p']) ? '?p=' . $params['_query']['p'] : '')
        );

        return new TaskList(
            $this->repository,
            $this->criteriaBuilder,
            $sortBuilder,
            $request,
            $timezone,
            $locale,
            new Status(),
            new Priority(),
            $url
        );
    }

    /**
     * @param array<string, mixed> $data
     * @return Task
     */
    private function task(array $data): Task
    {
        $task = (new ObjectManager($this))->getObject(Task::class);
        $task->setData($data + ['task_id' => 1, 'title' => 'T', 'status' => 0, 'priority' => 1]);
        return $task;
    }

    public function testRowsContainDisplayData(): void
    {
        $vm = $this->viewModel(1, [$this->task([
            'task_id' => 7, 'title' => 'Write <b>docs</b>', 'description' => "line1\nline2",
            'status' => 1, 'priority' => 2, 'due_date' => '2026-12-31',
        ])], 1);

        $row = $vm->getTasks()[0];
        $this->assertSame(7, $row['id']);
        $this->assertSame('Write <b>docs</b>', $row['title'], 'raw text; the template escapes it');
        $this->assertSame('In Progress', $row['status_label']);
        $this->assertSame('High', $row['priority_label']);
        $this->assertSame('Dec 31, 2026', $row['due_date_formatted']);
        $this->assertFalse($row['is_overdue']);
    }

    public function testOverdueRules(): void
    {
        $vm = $this->viewModel(1, [
            $this->task(['task_id' => 1, 'due_date' => '2026-06-14', 'status' => 0]),
            $this->task(['task_id' => 2, 'due_date' => '2026-06-15', 'status' => 0]),
            $this->task(['task_id' => 3, 'due_date' => '2026-06-14', 'status' => 2]),
            $this->task(['task_id' => 4, 'due_date' => null, 'status' => 0]),
        ], 4);

        $rows = $vm->getTasks();
        $this->assertTrue($rows[0]['is_overdue'], 'past due and not completed');
        $this->assertFalse($rows[1]['is_overdue'], 'due today is not overdue');
        $this->assertFalse($rows[2]['is_overdue'], 'completed tasks are never overdue');
        $this->assertFalse($rows[3]['is_overdue']);
        $this->assertSame('', $rows[3]['due_date_formatted']);
        $this->assertTrue($rows[2]['is_completed']);
    }

    public function testDueDateIsNotShiftedByTimezone(): void
    {
        $vm = $this->viewModel(1, [$this->task(['due_date' => '2026-01-01'])], 1);
        $this->assertSame('Jan 1, 2026', $vm->getTasks()[0]['due_date_formatted']);
    }

    public function testSortsOpenFirstThenPriorityThenDueDate(): void
    {
        $this->viewModel(1, [], 0)->getTasks();
        $this->assertSame(
            [['status', 'ASC'], ['priority', 'DESC'], ['due_date', 'ASC'], ['task_id', 'DESC']],
            $this->sorts
        );
        $this->assertSame(TaskList::PAGE_SIZE, $this->pageSize);
    }

    public function testEmptyList(): void
    {
        $vm = $this->viewModel(1, [], 0);
        $this->assertSame([], $vm->getTasks());
        $this->assertSame(0, $vm->getTotal());
        $this->assertSame(1, $vm->getTotalPages());
        $this->assertSame(1, $vm->getCurrentPage());
    }

    public function testPagingMath(): void
    {
        $vm = $this->viewModel(2, [$this->task([])], 25);
        $this->assertSame(3, $vm->getTotalPages());
        $this->assertSame(2, $vm->getCurrentPage());
        $vm->getTasks();
        $this->assertSame([2], $this->pagesRequested);
    }

    public function testPageBeyondTheEndFallsBackToLastPage(): void
    {
        $vm = $this->viewModel(99, [$this->task([])], 25);
        $vm->getTasks();
        $this->assertSame([99, 3], $this->pagesRequested);
        $this->assertSame(3, $vm->getCurrentPage());
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function badPageProvider(): array
    {
        return ['zero' => [0], 'negative' => [-4], 'text' => ['abc'], 'array' => [[1]]];
    }

    #[DataProvider('badPageProvider')]
    public function testBadPageParameterMeansPageOne(mixed $param): void
    {
        $vm = $this->viewModel($param, [$this->task([])], 25);
        $vm->getTasks();
        $this->assertSame(1, $vm->getCurrentPage());
        $this->assertSame([1], $this->pagesRequested);
    }

    public function testRepositoryIsQueriedOnlyOncePerRequest(): void
    {
        $vm = $this->viewModel(1, [$this->task([])], 1);
        $vm->getTasks();
        $vm->getTasks();
        $vm->getTotal();
        $this->assertCount(1, $this->pagesRequested);
    }

    public function testPageUrls(): void
    {
        $vm = $this->viewModel(1, [], 0);
        $this->assertSame('http://x/tasks', $vm->getPageUrl(1), 'page 1 has no query string');
        $this->assertSame('http://x/tasks?p=3', $vm->getPageUrl(3));
    }

    public function testPageNumberWindow(): void
    {
        $this->assertSame([1], $this->viewModel(1, [], 5)->getPageNumbers());
        $this->assertSame([1, 2, 3], $this->viewModel(1, [$this->task([])], 30)->getPageNumbers());
        // 100 pages, on page 50: first, window of 5 around current, last
        $middle = $this->viewModel(50, [$this->task([])], 1200);
        $this->assertSame([1, 48, 49, 50, 51, 52, 100], $middle->getPageNumbers());
        $this->assertSame([1, 2, 3, 100], $this->viewModel(1, [$this->task([])], 1200)->getPageNumbers());
    }
}

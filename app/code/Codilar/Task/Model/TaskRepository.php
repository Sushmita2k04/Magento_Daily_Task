<?php
declare(strict_types=1);

namespace Codilar\Task\Model;

use Codilar\Task\Api\Data\TaskInterface;
use Codilar\Task\Api\Data\TaskInterfaceFactory;
use Codilar\Task\Api\Data\TaskSearchResultsInterfaceFactory;
use Codilar\Task\Api\TaskRepositoryInterface;
use Codilar\Task\Model\ResourceModel\Task as TaskResource;
use Codilar\Task\Model\ResourceModel\Task\CollectionFactory;
use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * Saves, loads, searches and deletes tasks. Translates DB failures into Magento exceptions.
 */
class TaskRepository implements TaskRepositoryInterface
{
    /**
     * Fields a client may change. task_id, created_at and updated_at are never taken from input.
     */
    private const WRITABLE_FIELDS = [
        TaskInterface::TITLE,
        TaskInterface::DESCRIPTION,
        TaskInterface::STATUS,
        TaskInterface::PRIORITY,
        TaskInterface::DUE_DATE,
    ];

    /**
     * Fields where an explicit null/empty value means "clear it".
     */
    /**
     * Upper bound for one getList() page, so a client cannot request the whole table in one call.
     */
    public const MAX_PAGE_SIZE = 200;

    /**
     * Fields a search may filter or sort on.
     */
    private const SEARCHABLE_FIELDS = [
        TaskInterface::TASK_ID,
        TaskInterface::TITLE,
        TaskInterface::DESCRIPTION,
        TaskInterface::STATUS,
        TaskInterface::PRIORITY,
        TaskInterface::DUE_DATE,
        TaskInterface::IS_DONE,
        TaskInterface::CREATED_AT,
        TaskInterface::UPDATED_AT,
    ];

    private const CLEARABLE_FIELDS = [TaskInterface::DESCRIPTION, TaskInterface::DUE_DATE];

    /**
     * @param TaskResource $resource
     * @param TaskInterfaceFactory $taskFactory auto-generated factory for the data interface
     * @param TaskValidator $validator
     * @param CollectionFactory $collectionFactory
     * @param TaskSearchResultsInterfaceFactory $searchResultsFactory
     * @param CollectionProcessorInterface $collectionProcessor
     */
    public function __construct(
        private readonly TaskResource $resource,
        private readonly TaskInterfaceFactory $taskFactory,
        private readonly TaskValidator $validator,
        private readonly CollectionFactory $collectionFactory,
        private readonly TaskSearchResultsInterfaceFactory $searchResultsFactory,
        private readonly CollectionProcessorInterface $collectionProcessor
    ) {
    }

    /**
     * @inheritDoc
     */
    public function save(TaskInterface $task)
    {
        if (!$task instanceof Task) {
            throw new CouldNotSaveException(__('Unsupported task implementation.'));
        }

        $taskId = $task->getTaskId();
        $isUpdate = (bool) $taskId;

        // Partial updates are allowed, so a title is only mandatory when creating.
        $this->validator->validate($task, !$isUpdate);

        if ($isUpdate) {
            // Throws NoSuchEntityException instead of silently inserting a new row.
            $toSave = $this->applyChanges($this->loadModel($taskId), $task);
        } else {
            $toSave = $this->prepareNew($task);
        }

        try {
            $this->resource->save($toSave);
        } catch (\Exception $e) {
            throw new CouldNotSaveException(__('Could not save the task: %1', $e->getMessage()), $e);
        }

        // Reload so the response contains DB-generated values (task_id, created_at, updated_at).
        return $this->getById($toSave->getTaskId());
    }

    /**
     * @inheritDoc
     */
    public function update($taskId, TaskInterface $task)
    {
        $bodyId = $task->getTaskId();
        if ($bodyId && (int) $bodyId !== (int) $taskId) {
            throw new InputException(
                __('The "task_id" in the request body (%1) does not match the id in the URL (%2).', $bodyId, $taskId)
            );
        }
        $task->setTaskId((int) $taskId);
        return $this->save($task);
    }

    /**
     * @inheritDoc
     */
    public function getById($taskId)
    {
        return $this->loadModel($taskId);
    }

    /**
     * @inheritDoc
     */
    public function getList(SearchCriteriaInterface $searchCriteria)
    {
        $this->assertKnownFields($searchCriteria);

        $pageSize = (int) $searchCriteria->getPageSize();
        if ($pageSize < 1 || $pageSize > self::MAX_PAGE_SIZE) {
            $searchCriteria->setPageSize(self::MAX_PAGE_SIZE);
        }

        $collection = $this->collectionFactory->create();
        $this->collectionProcessor->process($searchCriteria, $collection);

        $searchResults = $this->searchResultsFactory->create();
        $searchResults->setSearchCriteria($searchCriteria);
        /** @var TaskInterface[] $items */
        $items = array_values($collection->getItems());
        $searchResults->setItems($items);
        $searchResults->setTotalCount($collection->getSize());
        return $searchResults;
    }

    /**
     * @inheritDoc
     */
    public function deleteById($taskId)
    {
        $task = $this->loadModel($taskId); // throws NoSuchEntityException if missing
        try {
            $this->resource->delete($task);
        } catch (\Exception $e) {
            throw new CouldNotDeleteException(__('Could not delete the task: %1', $e->getMessage()), $e);
        }
        return true;
    }

    /**
     * Reject searches on fields that are not task columns (they would otherwise surface a raw SQL error).
     *
     * @param SearchCriteriaInterface $searchCriteria
     * @return void
     * @throws InputException
     */
    private function assertKnownFields(SearchCriteriaInterface $searchCriteria): void
    {
        $unknown = [];
        foreach ((array) $searchCriteria->getFilterGroups() as $group) {
            foreach ((array) $group->getFilters() as $filter) {
                $unknown[] = $filter->getField();
            }
        }
        foreach ((array) $searchCriteria->getSortOrders() as $sortOrder) {
            $unknown[] = $sortOrder->getField();
        }
        $unknown = array_unique(array_diff($unknown, self::SEARCHABLE_FIELDS));

        if ($unknown) {
            throw new InputException(
                __('Unknown search field(s): %1.', implode(', ', array_map('strval', $unknown)))
            );
        }
    }

    /**
     * Load a task model by id.
     *
     * @param int|string $taskId
     * @return Task
     * @throws NoSuchEntityException
     */
    private function loadModel($taskId): Task
    {
        $task = $this->taskFactory->create();
        if (!$task instanceof Task) {
            throw new \LogicException('The TaskInterface preference must resolve to ' . Task::class);
        }
        $this->resource->load($task, $taskId);
        if (!$task->getTaskId()) {
            throw new NoSuchEntityException(__('Task with id "%1" does not exist.', $taskId));
        }
        return $task;
    }

    /**
     * Build the row to insert from client input, ignoring server-managed fields.
     *
     * @param Task $input
     * @return Task
     */
    private function prepareNew(Task $input): Task
    {
        $input->unsetData(TaskInterface::TASK_ID);
        $input->unsetData(TaskInterface::CREATED_AT);  // DB default applies
        $input->unsetData(TaskInterface::UPDATED_AT);
        $input->setTitle(trim((string) $input->getTitle()));
        // status / is_done / priority defaults are resolved in Task::beforeSave().
        return $input;
    }

    /**
     * Merge only the supplied fields onto the stored task.
     *
     * @param Task $existing
     * @param Task $input
     * @return Task
     */
    private function applyChanges(Task $existing, Task $input): Task
    {
        foreach (self::WRITABLE_FIELDS as $field) {
            if (!$input->hasData($field)) {
                continue;
            }
            $value = $input->getData($field);
            if (($value === null || $value === '') && !in_array($field, self::CLEARABLE_FIELDS, true)) {
                continue; // title/status/priority cannot be blanked
            }
            if ($field === TaskInterface::TITLE) {
                $value = trim((string) $value);
            }
            $existing->setData($field, $value === '' ? null : $value);
        }

        // Legacy client that sent is_done but no status: translate it onto status.
        if (!$this->hasValue($input, TaskInterface::STATUS) && $this->hasValue($input, TaskInterface::IS_DONE)) {
            if ((int) $input->getData(TaskInterface::IS_DONE) === 1) {
                $existing->setStatus(TaskInterface::STATUS_COMPLETED);
            } elseif ($existing->getStatus() === TaskInterface::STATUS_COMPLETED) {
                $existing->setStatus(TaskInterface::STATUS_PENDING);
            }
            // is_done = 0 on a task that is not completed changes nothing.
        }

        // Let the database maintain these; writing back the old value would freeze updated_at.
        $existing->unsetData(TaskInterface::UPDATED_AT);
        $existing->unsetData(TaskInterface::CREATED_AT);
        return $existing;
    }

    /**
     * Whether the field was supplied with a non-empty value.
     *
     * @param Task $task
     * @param string $field
     * @return bool
     */
    private function hasValue(Task $task, string $field): bool
    {
        $value = $task->getData($field);
        return $value !== null && $value !== '';
    }
}

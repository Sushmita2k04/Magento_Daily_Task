<?php
declare(strict_types=1);

namespace Codilar\Task\Api;

use Codilar\Task\Api\Data\TaskInterface;
use Magento\Framework\Api\SearchCriteriaInterface;

/**
 * Operations other code (and the REST API) may perform on tasks.
 *
 * @api
 */
interface TaskRepositoryInterface
{
    /**
     * Create a task, or update it when task_id is supplied.
     *
     * On update only the supplied fields change. status is the source of truth;
     * is_done is derived from it and a contradicting pair is rejected.
     *
     * @param \Codilar\Task\Api\Data\TaskInterface $task
     * @return \Codilar\Task\Api\Data\TaskInterface
     * @throws \Magento\Framework\Exception\InputException On invalid data
     * @throws \Magento\Framework\Exception\NoSuchEntityException When updating a missing task
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     */
    public function save(TaskInterface $task);

    /**
     * Update an existing task; only the supplied fields change.
     *
     * A task_id in the body that differs from $taskId is rejected.
     *
     * @param int $taskId
     * @param \Codilar\Task\Api\Data\TaskInterface $task
     * @return \Codilar\Task\Api\Data\TaskInterface
     * @throws \Magento\Framework\Exception\InputException On invalid data or an id mismatch
     * @throws \Magento\Framework\Exception\NoSuchEntityException When the task does not exist
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     */
    public function update($taskId, TaskInterface $task);

    /**
     * Load one task by its id.
     *
     * @param int $taskId
     * @return \Codilar\Task\Api\Data\TaskInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getById($taskId);

    /**
     * Retrieve tasks matching the search criteria.
     *
     * @param \Magento\Framework\Api\SearchCriteriaInterface $searchCriteria
     * @return \Codilar\Task\Api\Data\TaskSearchResultsInterface
     */
    public function getList(SearchCriteriaInterface $searchCriteria);

    /**
     * Delete one task by its id.
     *
     * @param int $taskId
     * @return bool true on success
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Magento\Framework\Exception\CouldNotDeleteException
     */
    public function deleteById($taskId);
}

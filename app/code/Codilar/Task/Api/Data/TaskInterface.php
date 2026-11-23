<?php
declare(strict_types=1);

namespace Codilar\Task\Api\Data;

/**
 * Data contract for a task. Magento's REST layer reads the docblock types
 * below to build the JSON request/response, so they must stay accurate.
 *
 * `status` is the source of truth. `is_done` is a legacy flag that is always
 * derived from it (1 only when status is Completed).
 *
 * @api
 */
interface TaskInterface
{
    public const TASK_ID = 'task_id';
    public const TITLE = 'title';
    public const DESCRIPTION = 'description';
    public const STATUS = 'status';
    public const PRIORITY = 'priority';
    public const DUE_DATE = 'due_date';
    public const IS_DONE = 'is_done';
    public const CREATED_AT = 'created_at';
    public const UPDATED_AT = 'updated_at';

    public const STATUS_PENDING = 0;
    public const STATUS_IN_PROGRESS = 1;
    public const STATUS_COMPLETED = 2;

    public const PRIORITY_LOW = 0;
    public const PRIORITY_MEDIUM = 1;
    public const PRIORITY_HIGH = 2;

    public const TITLE_MAX_LENGTH = 255;
    public const DESCRIPTION_MAX_LENGTH = 65535;

    /**
     * Get task ID.
     *
     * @return int|null
     */
    public function getTaskId();

    /**
     * Set task ID.
     *
     * @param int $taskId
     * @return $this
     */
    public function setTaskId($taskId);

    /**
     * Get title.
     *
     * @return string|null
     */
    public function getTitle();

    /**
     * Set title.
     *
     * @param string $title
     * @return $this
     */
    public function setTitle($title);

    /**
     * Get description.
     *
     * @return string|null
     */
    public function getDescription();

    /**
     * Set description.
     *
     * @param string|null $description
     * @return $this
     */
    public function setDescription($description);

    /**
     * 0 = Pending, 1 = In Progress, 2 = Completed.
     *
     * @return int|null
     */
    public function getStatus();

    /**
     * Set status.
     *
     * @param int $status
     * @return $this
     */
    public function setStatus($status);

    /**
     * 0 = Low, 1 = Medium, 2 = High.
     *
     * @return int|null
     */
    public function getPriority();

    /**
     * Set priority.
     *
     * @param int $priority
     * @return $this
     */
    public function setPriority($priority);

    /**
     * Due date as Y-m-d, or null.
     *
     * @return string|null
     */
    public function getDueDate();

    /**
     * Set due date.
     *
     * @param string|null $dueDate
     * @return $this
     */
    public function setDueDate($dueDate);

    /**
     * Legacy flag, derived from status: 1 only when status is Completed.
     *
     * @return int
     */
    public function getIsDone();

    /**
     * Legacy setter. Only honoured when no status is supplied; see Model\Task.
     *
     * @param int $isDone
     * @return $this
     */
    public function setIsDone($isDone);

    /**
     * Get created at.
     *
     * @return string|null
     */
    public function getCreatedAt();

    /**
     * Set created at.
     *
     * @param string $createdAt
     * @return $this
     */
    public function setCreatedAt($createdAt);

    /**
     * Read-only: maintained by the database; ignored on input.
     *
     * @return string|null
     */
    public function getUpdatedAt();

    /**
     * Set updated at.
     *
     * @param string $updatedAt
     * @return $this
     */
    public function setUpdatedAt($updatedAt);
}

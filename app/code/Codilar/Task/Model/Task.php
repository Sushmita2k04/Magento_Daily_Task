<?php
declare(strict_types=1);

namespace Codilar\Task\Model;

use Codilar\Task\Api\Data\TaskInterface;
use Codilar\Task\Model\ResourceModel\Task as TaskResource;
use Magento\Framework\DataObject\IdentityInterface;
use Magento\Framework\Model\AbstractModel;

/**
 * Model: one row of `codilar_task` as a PHP object.
 *
 * Status / is_done rule (status is the single source of truth):
 *  - getIsDone() is always derived from status when a status is present.
 *  - beforeSave() runs for every save path (admin, REST, repository, direct
 *    resource save) and rewrites is_done from status, so the two columns can
 *    never be persisted inconsistently.
 *  - Legacy clients that send only is_done get a status derived from it.
 *  - Conflicting input (both supplied, disagreeing) is rejected by
 *    TaskValidator before it reaches this class.
 */
class Task extends AbstractModel implements TaskInterface, IdentityInterface
{
    /**
     * Cache tag; cleaning it refreshes the cached storefront task list.
     */
    public const CACHE_TAG = 'codilar_task';

    /**
     * @var string
     */
    protected $_cacheTag = self::CACHE_TAG;

    /**
     * @inheritDoc
     */
    protected function _construct()
    {
        // Tell the model which resource model loads/saves it.
        $this->_init(TaskResource::class);
    }

    /**
     * Cache tags to clean when this task is saved or deleted.
     *
     * @return string[]
     */
    public function getIdentities()
    {
        return array_filter([self::CACHE_TAG, $this->getId() ? self::CACHE_TAG . '_' . $this->getId() : null]);
    }

    /**
     * Normalise status/is_done (and defaults on create) right before writing.
     *
     * @return $this
     */
    public function beforeSave()
    {
        $status = $this->getData(self::STATUS);
        $isDone = $this->getData(self::IS_DONE);
        $isNew = !$this->getData(self::TASK_ID);

        if ($status === null || $status === '') {
            if ($isDone !== null && $isDone !== '') {
                // Legacy client: derive status from the old flag.
                $status = (int) $isDone === 1 ? self::STATUS_COMPLETED : self::STATUS_PENDING;
            } elseif ($isNew) {
                $status = self::STATUS_PENDING;
            } else {
                $status = null; // partial update that does not touch status
            }
        }

        if ($status !== null) {
            $status = (int) $status;
            $this->setData(self::STATUS, $status);
            $this->setData(self::IS_DONE, $status === self::STATUS_COMPLETED ? 1 : 0);
        }

        if ($isNew && $this->getData(self::PRIORITY) === null) {
            $this->setData(self::PRIORITY, self::PRIORITY_MEDIUM);
        }

        return parent::beforeSave();
    }

    /**
     * @inheritDoc
     */
    public function getTaskId()
    {
        $id = $this->getData(self::TASK_ID);
        return $id === null ? null : (int) $id;
    }

    /**
     * @inheritDoc
     */
    public function setTaskId($taskId)
    {
        return $this->setData(self::TASK_ID, $taskId);
    }

    /**
     * @inheritDoc
     */
    public function getTitle()
    {
        return $this->getData(self::TITLE);
    }

    /**
     * @inheritDoc
     */
    public function setTitle($title)
    {
        return $this->setData(self::TITLE, $title);
    }

    /**
     * @inheritDoc
     */
    public function getDescription()
    {
        return $this->getData(self::DESCRIPTION);
    }

    /**
     * @inheritDoc
     */
    public function setDescription($description)
    {
        return $this->setData(self::DESCRIPTION, $description);
    }

    /**
     * @inheritDoc
     */
    public function getStatus()
    {
        $status = $this->getData(self::STATUS);
        return ($status === null || $status === '') ? null : (int) $status;
    }

    /**
     * @inheritDoc
     */
    public function setStatus($status)
    {
        return $this->setData(self::STATUS, $status);
    }

    /**
     * @inheritDoc
     */
    public function getPriority()
    {
        $priority = $this->getData(self::PRIORITY);
        return ($priority === null || $priority === '') ? null : (int) $priority;
    }

    /**
     * @inheritDoc
     */
    public function setPriority($priority)
    {
        return $this->setData(self::PRIORITY, $priority);
    }

    /**
     * @inheritDoc
     */
    public function getDueDate()
    {
        $dueDate = $this->getData(self::DUE_DATE);
        return ($dueDate === null || $dueDate === '') ? null : (string) $dueDate;
    }

    /**
     * @inheritDoc
     */
    public function setDueDate($dueDate)
    {
        return $this->setData(self::DUE_DATE, $dueDate === '' ? null : $dueDate);
    }

    /**
     * Derived from status whenever a status exists, so it is never stale in memory.
     *
     * @return int
     */
    public function getIsDone()
    {
        $status = $this->getStatus();
        if ($status !== null) {
            return $status === self::STATUS_COMPLETED ? 1 : 0;
        }
        return (int) $this->getData(self::IS_DONE);
    }

    /**
     * Stores the raw legacy value; it only takes effect when no status is supplied.
     *
     * @param int $isDone
     * @return $this
     */
    public function setIsDone($isDone)
    {
        return $this->setData(self::IS_DONE, $isDone);
    }

    /**
     * @inheritDoc
     */
    public function getCreatedAt()
    {
        return $this->getData(self::CREATED_AT);
    }

    /**
     * @inheritDoc
     */
    public function setCreatedAt($createdAt)
    {
        return $this->setData(self::CREATED_AT, $createdAt);
    }

    /**
     * @inheritDoc
     */
    public function getUpdatedAt()
    {
        return $this->getData(self::UPDATED_AT);
    }

    /**
     * @inheritDoc
     */
    public function setUpdatedAt($updatedAt)
    {
        return $this->setData(self::UPDATED_AT, $updatedAt);
    }
}

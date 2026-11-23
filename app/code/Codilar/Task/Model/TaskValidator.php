<?php
declare(strict_types=1);

namespace Codilar\Task\Model;

use Codilar\Task\Api\Data\TaskInterface;
use Codilar\Task\Model\Source\Priority;
use Codilar\Task\Model\Source\Status;
use Magento\Framework\Exception\InputException;

/**
 * Validates task data coming from the Admin, REST API or other callers.
 *
 * Only fields that were actually supplied are checked (so partial updates
 * work); pass $requireTitle = true when the data must describe a whole task.
 */
class TaskValidator
{
    /**
     * @param Status $statusSource
     * @param Priority $prioritySource
     */
    public function __construct(
        private readonly Status $statusSource,
        private readonly Priority $prioritySource
    ) {
    }

    /**
     * Validate the supplied task data.
     *
     * @param Task $task
     * @param bool $requireTitle
     * @return void
     * @throws InputException with one error per problem found
     */
    public function validate(Task $task, bool $requireTitle = true): void
    {
        $exception = new InputException();

        $this->validateTitle($task, $requireTitle, $exception);
        $this->validateDescription($task, $exception);
        $this->validateStatusAndFlag($task, $exception);
        $this->validatePriority($task, $exception);
        $this->validateDueDate($task, $exception);

        if ($exception->wasErrorAdded()) {
            throw $exception;
        }
    }

    /**
     * Check the title.
     *
     * @param Task $task
     * @param bool $requireTitle
     * @param InputException $exception
     * @return void
     */
    private function validateTitle(Task $task, bool $requireTitle, InputException $exception): void
    {
        $title = $task->getData(TaskInterface::TITLE);
        if ($title === null) {
            if ($requireTitle) {
                $exception->addError(__('"%fieldName" is required.', ['fieldName' => TaskInterface::TITLE]));
            }
            return;
        }
        if (!is_string($title) || trim($title) === '') {
            $exception->addError(__('"%fieldName" must not be empty.', ['fieldName' => TaskInterface::TITLE]));
        } elseif (mb_strlen(trim($title)) > TaskInterface::TITLE_MAX_LENGTH) {
            $exception->addError(
                __(
                    '"%fieldName" must be at most %max characters.',
                    ['fieldName' => TaskInterface::TITLE, 'max' => TaskInterface::TITLE_MAX_LENGTH]
                )
            );
        }
    }

    /**
     * Check the description.
     *
     * @param Task $task
     * @param InputException $exception
     * @return void
     */
    private function validateDescription(Task $task, InputException $exception): void
    {
        $description = $task->getData(TaskInterface::DESCRIPTION);
        if ($description === null) {
            return;
        }
        if (!is_string($description)) {
            $exception->addError(
                __('"%fieldName" must be text.', ['fieldName' => TaskInterface::DESCRIPTION])
            );
        } elseif (strlen($description) > TaskInterface::DESCRIPTION_MAX_LENGTH) {
            $exception->addError(
                __(
                    '"%fieldName" must be at most %max bytes.',
                    ['fieldName' => TaskInterface::DESCRIPTION, 'max' => TaskInterface::DESCRIPTION_MAX_LENGTH]
                )
            );
        }
    }

    /**
     * Checks status, the legacy is_done flag, and that the two do not contradict.
     *
     * @param Task $task
     * @param InputException $exception
     * @return void
     */
    private function validateStatusAndFlag(Task $task, InputException $exception): void
    {
        $status = $task->getData(TaskInterface::STATUS);
        $isDone = $task->getData(TaskInterface::IS_DONE);
        $hasStatus = $status !== null && $status !== '';
        $hasIsDone = $isDone !== null && $isDone !== '';
        $statusOk = true;
        $isDoneOk = true;

        if ($hasStatus && !$this->statusSource->isValid($status)) {
            $statusOk = false;
            $exception->addError(
                __('"%fieldName" must be 0 (Pending), 1 (In Progress) or 2 (Completed).', ['fieldName' => 'status'])
            );
        }
        if ($hasIsDone && !in_array($isDone, [0, 1, '0', '1'], true)) {
            $isDoneOk = false;
            $exception->addError(
                __('"%fieldName" must be 0 or 1.', ['fieldName' => TaskInterface::IS_DONE])
            );
        }

        if ($hasStatus && $hasIsDone && $statusOk && $isDoneOk) {
            $expected = (int) $status === TaskInterface::STATUS_COMPLETED ? 1 : 0;
            if ((int) $isDone !== $expected) {
                $exception->addError(
                    __(
                        '"is_done" (%isDone) contradicts "status" (%status). '
                        . 'Send only "status", or make them agree: is_done is 1 only when status is 2.',
                        ['isDone' => (int) $isDone, 'status' => (int) $status]
                    )
                );
            }
        }
    }

    /**
     * Check the priority.
     *
     * @param Task $task
     * @param InputException $exception
     * @return void
     */
    private function validatePriority(Task $task, InputException $exception): void
    {
        $priority = $task->getData(TaskInterface::PRIORITY);
        if ($priority === null || $priority === '') {
            return;
        }
        if (!$this->prioritySource->isValid($priority)) {
            $exception->addError(
                __('"%fieldName" must be 0 (Low), 1 (Medium) or 2 (High).', ['fieldName' => TaskInterface::PRIORITY])
            );
        }
    }

    /**
     * Check the due date.
     *
     * @param Task $task
     * @param InputException $exception
     * @return void
     */
    private function validateDueDate(Task $task, InputException $exception): void
    {
        $dueDate = $task->getData(TaskInterface::DUE_DATE);
        if ($dueDate === null || $dueDate === '') {
            return;
        }
        $parsed = is_string($dueDate) ? \DateTimeImmutable::createFromFormat('!Y-m-d', $dueDate) : false;
        if ($parsed === false || $parsed->format('Y-m-d') !== $dueDate) {
            $exception->addError(
                __('"%fieldName" must be a valid date in Y-m-d format.', ['fieldName' => TaskInterface::DUE_DATE])
            );
        }
    }
}

<?php
declare(strict_types=1);

namespace Codilar\Task\Controller\Adminhtml\Task;

use Codilar\Task\Api\Data\TaskInterface;
use Codilar\Task\Api\Data\TaskInterfaceFactory;
use Codilar\Task\Api\TaskRepositoryInterface;
use Codilar\Task\Model\DateInputConverter;
use Magento\Backend\App\Action;
use Magento\Backend\Model\View\Result\Redirect;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Psr\Log\LoggerInterface;

/**
 * Creates or updates a task from the admin form. All rules (validation, status/is_done sync)
 * live in the repository, so Admin, REST and other callers behave identically.
 */
class Save extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Codilar_Task::task_save';
    public const PERSISTOR_KEY = 'codilar_task';

    /**
     * @param Action\Context $context
     * @param TaskRepositoryInterface $repository
     * @param TaskInterfaceFactory $taskFactory
     * @param DataPersistorInterface $dataPersistor
     * @param DateInputConverter $dateConverter converts the admin's locale date to Y-m-d
     * @param LoggerInterface $logger
     */
    public function __construct(
        Action\Context $context,
        private readonly TaskRepositoryInterface $repository,
        private readonly TaskInterfaceFactory $taskFactory,
        private readonly DataPersistorInterface $dataPersistor,
        private readonly DateInputConverter $dateConverter,
        private readonly LoggerInterface $logger
    ) {
        parent::__construct($context);
    }

    /**
     * @inheritDoc
     */
    public function execute()
    {
        /** @var Redirect $redirect */
        $redirect = $this->resultRedirectFactory->create();
        $data = (array) $this->getRequest()->getPostValue();
        if (!$data) {
            return $redirect->setPath('*/*/');
        }
        $id = (int) ($data[TaskInterface::TASK_ID] ?? 0);

        try {
            $task = $this->taskFactory->create();
            if ($id) {
                $task->setTaskId($id);
            }
            $task->setTitle((string) ($data[TaskInterface::TITLE] ?? ''));
            $description = (string) ($data[TaskInterface::DESCRIPTION] ?? '');
            $task->setDescription($description === '' ? null : $description);
            $task->setStatus($data[TaskInterface::STATUS] ?? TaskInterface::STATUS_PENDING);
            $task->setPriority($data[TaskInterface::PRIORITY] ?? TaskInterface::PRIORITY_MEDIUM);
            $task->setDueDate($this->normalizeDueDate((string) ($data[TaskInterface::DUE_DATE] ?? '')));
            // is_done is never taken from the form: it is derived from status.

            $saved = $this->repository->save($task);
            $this->dataPersistor->clear(self::PERSISTOR_KEY);
            $this->messageManager->addSuccessMessage(__('The task has been saved.'));

            if ($this->getRequest()->getParam('back') === 'edit') {
                return $redirect->setPath('*/*/edit', [TaskInterface::TASK_ID => $saved->getTaskId()]);
            }
            return $redirect->setPath('*/*/');
        } catch (NoSuchEntityException) {
            $this->messageManager->addErrorMessage(__('This task no longer exists.'));
            return $redirect->setPath('*/*/');
        } catch (InputException $e) {
            foreach ($e->getErrors() ?: [$e] as $error) {
                $this->messageManager->addErrorMessage($error->getMessage());
            }
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        } catch (\Throwable $e) {
            $this->logger->error($e);
            $this->messageManager->addErrorMessage(__('Something went wrong while saving the task.'));
        }

        // Keep what the admin typed so the form can be re-filled.
        $this->dataPersistor->set(self::PERSISTOR_KEY, $data);
        return $id
            ? $redirect->setPath('*/*/edit', [TaskInterface::TASK_ID => $id])
            : $redirect->setPath('*/*/new');
    }

    /**
     * The date picker posts the admin's locale format (e.g. 12/31/26); the repository wants Y-m-d.
     *
     * Values that are not a real date are passed on unchanged so validation reports them.
     *
     * @param string $value
     * @return string
     */
    private function normalizeDueDate(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        return $this->dateConverter->toIsoDate($value) ?? $value;
    }
}

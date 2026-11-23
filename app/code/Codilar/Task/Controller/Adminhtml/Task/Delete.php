<?php
declare(strict_types=1);

namespace Codilar\Task\Controller\Adminhtml\Task;

use Codilar\Task\Api\Data\TaskInterface;
use Codilar\Task\Api\TaskRepositoryInterface;
use Magento\Backend\App\Action;
use Magento\Backend\Model\View\Result\Redirect;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Psr\Log\LoggerInterface;

/**
 * Deletes one task (POST only, CSRF-protected by the admin form key).
 */
class Delete extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Codilar_Task::task_delete';

    /**
     * @param Action\Context $context
     * @param TaskRepositoryInterface $repository
     * @param LoggerInterface $logger
     */
    public function __construct(
        Action\Context $context,
        private readonly TaskRepositoryInterface $repository,
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
        $id = (int) $this->getRequest()->getParam(TaskInterface::TASK_ID);

        if (!$id) {
            $this->messageManager->addErrorMessage(__('We can\'t find a task to delete.'));
            return $redirect->setPath('*/*/');
        }

        try {
            $this->repository->deleteById($id);
            $this->messageManager->addSuccessMessage(__('The task has been deleted.'));
        } catch (NoSuchEntityException) {
            $this->messageManager->addErrorMessage(__('This task no longer exists.'));
        } catch (LocalizedException $e) {
            $this->logger->error($e);
            $this->messageManager->addErrorMessage(__('The task could not be deleted.'));
            return $redirect->setPath('*/*/edit', [TaskInterface::TASK_ID => $id]);
        }
        return $redirect->setPath('*/*/');
    }
}

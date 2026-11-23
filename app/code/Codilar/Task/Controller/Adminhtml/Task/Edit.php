<?php
declare(strict_types=1);

namespace Codilar\Task\Controller\Adminhtml\Task;

use Codilar\Task\Api\TaskRepositoryInterface;
use Magento\Backend\App\Action;
use Magento\Backend\Model\View\Result\Page;
use Magento\Backend\Model\View\Result\Redirect;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Result\PageFactory;

/**
 * Create / edit form. Viewing an existing task needs "view"; the blank form needs "save".
 */
class Edit extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Codilar_Task::task_view';
    public const SAVE_RESOURCE = 'Codilar_Task::task_save';

    /**
     * @param Action\Context $context
     * @param PageFactory $resultPageFactory
     * @param TaskRepositoryInterface $repository
     */
    public function __construct(
        Action\Context $context,
        private readonly PageFactory $resultPageFactory,
        private readonly TaskRepositoryInterface $repository
    ) {
        parent::__construct($context);
    }

    /**
     * @inheritDoc
     */
    protected function _isAllowed()
    {
        $resource = $this->getRequest()->getParam('task_id') ? self::ADMIN_RESOURCE : self::SAVE_RESOURCE;
        return $this->_authorization->isAllowed($resource);
    }

    /**
     * @inheritDoc
     */
    public function execute()
    {
        $id = (int) $this->getRequest()->getParam('task_id');
        $title = __('New Task');

        if ($id) {
            try {
                $title = __('Edit Task: %1', $this->repository->getById($id)->getTitle());
            } catch (NoSuchEntityException) {
                $this->messageManager->addErrorMessage(__('This task no longer exists.'));
                /** @var Redirect $redirect */
                $redirect = $this->resultRedirectFactory->create();
                return $redirect->setPath('*/*/');
            }
        }

        /** @var Page $page */
        $page = $this->resultPageFactory->create();
        $page->setActiveMenu($id ? 'Codilar_Task::task_manage' : 'Codilar_Task::task_add');
        $page->getConfig()->getTitle()->prepend($title);
        return $page;
    }
}

<?php
declare(strict_types=1);

namespace Codilar\Task\Controller\Adminhtml\Task;

use Magento\Backend\App\Action;
use Magento\Backend\Model\View\Result\Page;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\View\Result\PageFactory;

/**
 * Admin task grid.
 */
class Index extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Codilar_Task::task_view';

    /**
     * @param Action\Context $context
     * @param PageFactory $resultPageFactory
     */
    public function __construct(
        Action\Context $context,
        private readonly PageFactory $resultPageFactory
    ) {
        parent::__construct($context);
    }

    /**
     * @inheritDoc
     */
    public function execute()
    {
        /** @var Page $page */
        $page = $this->resultPageFactory->create();
        $page->setActiveMenu('Codilar_Task::task_manage');
        $page->getConfig()->getTitle()->prepend(__('Manage Tasks'));
        return $page;
    }
}

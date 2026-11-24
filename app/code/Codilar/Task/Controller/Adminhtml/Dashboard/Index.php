<?php
declare(strict_types=1);

namespace Codilar\Task\Controller\Adminhtml\Dashboard;

use Magento\Backend\App\Action;
use Magento\Backend\Model\View\Result\Page;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\View\Result\PageFactory;

/**
 * Task analytics dashboard: the landing page of the Tasks menu.
 */
class Index extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Codilar_Task::task_dashboard';

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
        $page->setActiveMenu('Codilar_Task::task_dashboard');
        $page->getConfig()->getTitle()->prepend(__('Task Dashboard'));
        return $page;
    }
}

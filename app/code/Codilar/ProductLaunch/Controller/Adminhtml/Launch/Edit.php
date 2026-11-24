<?php
declare(strict_types=1);

namespace Codilar\ProductLaunch\Controller\Adminhtml\Launch;

use Codilar\ProductLaunch\Api\LaunchRepositoryInterface;
use Magento\Backend\App\Action;
use Magento\Backend\Model\View\Result\Page;
use Magento\Backend\Model\View\Result\Redirect;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Result\PageFactory;

class Edit extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Codilar_ProductLaunch::launch_save';

    public function __construct(
        Action\Context $context,
        private readonly PageFactory $resultPageFactory,
        private readonly LaunchRepositoryInterface $repository
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $id = (int)$this->getRequest()->getParam('launch_id');
        $title = __('New Launch');
        if ($id) {
            try {
                $title = $this->repository->getById($id)->getTitle();
            } catch (NoSuchEntityException) {
                $this->messageManager->addErrorMessage(__('This launch no longer exists.'));
                /** @var Redirect $redirect */
                $redirect = $this->resultRedirectFactory->create();
                return $redirect->setPath('*/*/');
            }
        }
        /** @var Page $page */
        $page = $this->resultPageFactory->create();
        $page->setActiveMenu('Codilar_ProductLaunch::launch_manage');
        $page->getConfig()->getTitle()->prepend(__('Product Launches'));
        $page->getConfig()->getTitle()->prepend($title);
        return $page;
    }
}

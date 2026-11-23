<?php
declare(strict_types=1);

namespace Codilar\ProductLaunch\Controller\Adminhtml\Launch;

use Codilar\ProductLaunch\Api\LaunchRepositoryInterface;
use Magento\Backend\App\Action;
use Magento\Backend\Model\View\Result\Redirect;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Exception\LocalizedException;

class Delete extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Codilar_ProductLaunch::launch_delete';

    public function __construct(Action\Context $context, private readonly LaunchRepositoryInterface $repository)
    {
        parent::__construct($context);
    }

    public function execute()
    {
        /** @var Redirect $redirect */
        $redirect = $this->resultRedirectFactory->create();
        $id = (int)$this->getRequest()->getParam('launch_id');
        try {
            $this->repository->deleteById($id);
            $this->messageManager->addSuccessMessage(__('The launch has been deleted.'));
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        } catch (\Throwable) {
            $this->messageManager->addErrorMessage(__('Something went wrong while deleting the launch.'));
        }
        return $redirect->setPath('*/*/');
    }
}

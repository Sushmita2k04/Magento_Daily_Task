<?php
declare(strict_types=1);

namespace Codilar\ProductLaunch\Controller\Adminhtml\Launch;

use Codilar\ProductLaunch\Api\LaunchRepositoryInterface;
use Codilar\ProductLaunch\Model\ResourceModel\Launch\CollectionFactory;
use Magento\Backend\App\Action;
use Magento\Backend\Model\View\Result\Redirect;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Ui\Component\MassAction\Filter;

class MassDelete extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Codilar_ProductLaunch::launch_delete';

    public function __construct(
        Action\Context $context,
        private readonly Filter $filter,
        private readonly CollectionFactory $collectionFactory,
        private readonly LaunchRepositoryInterface $repository
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        /** @var Redirect $redirect */
        $redirect = $this->resultRedirectFactory->create();
        try {
            $collection = $this->filter->getCollection($this->collectionFactory->create());
            $count = 0;
            foreach ($collection as $launch) {
                $this->repository->delete($launch);
                $count++;
            }
            $this->messageManager->addSuccessMessage(__('%1 launch(es) have been deleted.', $count));
        } catch (\Throwable $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        }
        return $redirect->setPath('*/*/');
    }
}

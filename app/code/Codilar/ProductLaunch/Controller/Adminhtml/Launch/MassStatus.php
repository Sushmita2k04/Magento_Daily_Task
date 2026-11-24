<?php
declare(strict_types=1);

namespace Codilar\ProductLaunch\Controller\Adminhtml\Launch;

use Codilar\ProductLaunch\Api\LaunchRepositoryInterface;
use Codilar\ProductLaunch\Model\ResourceModel\Launch\CollectionFactory;
use Magento\Backend\App\Action;
use Magento\Backend\Model\View\Result\Redirect;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Ui\Component\MassAction\Filter;

/**
 * Enable / disable one or many launches (param "enable" = 1|0).
 */
class MassStatus extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Codilar_ProductLaunch::launch_save';

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
        $enable = (bool)(int)$this->getRequest()->getParam('enable');
        try {
            $collection = $this->filter->getCollection($this->collectionFactory->create());
            $count = 0;
            $skipped = 0;
            foreach ($collection as $launch) {
                try {
                    $launch->setIsActive($enable);
                    $this->repository->save($launch);
                    $count++;
                } catch (\Throwable) {
                    $skipped++; // e.g. its product was deleted: needs manual fix in the edit form
                }
            }
            $this->messageManager->addSuccessMessage(
                $enable ? __('%1 launch(es) enabled.', $count) : __('%1 launch(es) disabled.', $count)
            );
            if ($skipped) {
                $this->messageManager->addWarningMessage(
                    __('%1 launch(es) could not be updated (open them to fix the product or dates).', $skipped)
                );
            }
        } catch (\Throwable $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        }
        return $redirect->setPath('*/*/');
    }
}

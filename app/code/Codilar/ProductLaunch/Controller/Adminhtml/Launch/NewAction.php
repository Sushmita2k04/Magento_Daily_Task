<?php
declare(strict_types=1);

namespace Codilar\ProductLaunch\Controller\Adminhtml\Launch;

use Magento\Backend\App\Action;
use Magento\Backend\Model\View\Result\Forward;
use Magento\Backend\Model\View\Result\ForwardFactory;
use Magento\Framework\App\Action\HttpGetActionInterface;

class NewAction extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Codilar_ProductLaunch::launch_save';

    public function __construct(Action\Context $context, private readonly ForwardFactory $forwardFactory)
    {
        parent::__construct($context);
    }

    public function execute()
    {
        /** @var Forward $forward */
        $forward = $this->forwardFactory->create();
        return $forward->forward('edit');
    }
}

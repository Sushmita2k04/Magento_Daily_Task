<?php
declare(strict_types=1);

namespace Codilar\Task\Controller\Adminhtml\Task;

use Magento\Backend\App\Action;
use Magento\Backend\Model\View\Result\Forward;
use Magento\Backend\Model\View\Result\ForwardFactory;
use Magento\Framework\App\Action\HttpGetActionInterface;

/**
 * "Add New Task": shows the (empty) edit form.
 */
class NewAction extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Codilar_Task::task_save';

    /**
     * @param Action\Context $context
     * @param ForwardFactory $resultForwardFactory
     */
    public function __construct(
        Action\Context $context,
        private readonly ForwardFactory $resultForwardFactory
    ) {
        parent::__construct($context);
    }

    /**
     * @inheritDoc
     */
    public function execute()
    {
        /** @var Forward $forward */
        $forward = $this->resultForwardFactory->create();
        return $forward->forward('edit');
    }
}

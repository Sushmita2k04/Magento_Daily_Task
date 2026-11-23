<?php
declare(strict_types=1);

namespace Codilar\Task\Block\Adminhtml\Task;

use Magento\Framework\AuthorizationInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;

/**
 * "Add New Task" button on the grid, only for users who may create tasks.
 */
class AddButton implements ButtonProviderInterface
{
    /**
     * @param UrlInterface $urlBuilder
     * @param AuthorizationInterface $authorization
     */
    public function __construct(
        private readonly UrlInterface $urlBuilder,
        private readonly AuthorizationInterface $authorization
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getButtonData()
    {
        if (!$this->authorization->isAllowed('Codilar_Task::task_save')) {
            return [];
        }
        return [
            'label' => __('Add New Task'),
            'class' => 'primary',
            'on_click' => sprintf("location.href = '%s'", $this->urlBuilder->getUrl('*/*/new')),
            'sort_order' => 10,
        ];
    }
}

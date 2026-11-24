<?php
declare(strict_types=1);

namespace Codilar\Task\Block\Adminhtml\Task\Edit;

use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;

/**
 * "Save" button, only for users who may save tasks.
 */
class SaveButton extends GenericButton implements ButtonProviderInterface
{
    /**
     * @inheritDoc
     */
    public function getButtonData()
    {
        if (!$this->authorization->isAllowed('Codilar_Task::task_save')) {
            return [];
        }
        return [
            'label' => __('Save Task'),
            'class' => 'save primary',
            'data_attribute' => [
                'mage-init' => ['button' => ['event' => 'save']],
                'form-role' => 'save',
            ],
            'sort_order' => 90,
        ];
    }
}

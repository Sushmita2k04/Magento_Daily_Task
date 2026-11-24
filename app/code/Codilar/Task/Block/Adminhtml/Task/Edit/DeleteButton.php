<?php
declare(strict_types=1);

namespace Codilar\Task\Block\Adminhtml\Task\Edit;

use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;

/**
 * "Delete" button: only for an existing task and a user with the delete permission.
 */
class DeleteButton extends GenericButton implements ButtonProviderInterface
{
    /**
     * @inheritDoc
     */
    public function getButtonData()
    {
        if (!$this->getTaskId() || !$this->authorization->isAllowed('Codilar_Task::task_delete')) {
            return [];
        }
        $message = __('Are you sure you want to delete this task? This cannot be undone.');
        return [
            'label' => __('Delete Task'),
            'class' => 'delete',
            'on_click' => sprintf(
                "deleteConfirm('%s', '%s', {data: {}})",
                $message,
                $this->getUrl('*/*/delete', ['task_id' => $this->getTaskId()])
            ),
            'sort_order' => 20,
        ];
    }
}

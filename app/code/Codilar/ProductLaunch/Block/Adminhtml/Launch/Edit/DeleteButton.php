<?php
declare(strict_types=1);

namespace Codilar\ProductLaunch\Block\Adminhtml\Launch\Edit;

use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;

class DeleteButton extends GenericButton implements ButtonProviderInterface
{
    public function getButtonData(): array
    {
        if (!$this->getLaunchId() || !$this->authorization->isAllowed('Codilar_ProductLaunch::launch_delete')) {
            return [];
        }
        $message = __('Are you sure you want to delete this launch? This cannot be undone.');
        return [
            'label' => __('Delete Launch'),
            'class' => 'delete',
            'on_click' => sprintf(
                "deleteConfirm('%s', '%s', {data: {}})",
                $message,
                $this->getUrl('*/*/delete', ['launch_id' => $this->getLaunchId()])
            ),
            'sort_order' => 20,
        ];
    }
}

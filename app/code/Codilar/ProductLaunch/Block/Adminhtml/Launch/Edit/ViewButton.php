<?php
declare(strict_types=1);

namespace Codilar\ProductLaunch\Block\Adminhtml\Launch\Edit;

use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;

class ViewButton extends GenericButton implements ButtonProviderInterface
{
    public function getButtonData(): array
    {
        if (!$this->getLaunchId()) {
            return [];
        }
        return [
            'label' => __('View Details'),
            'on_click' => sprintf("location.href = '%s';", $this->getUrl('*/*/view', ['launch_id' => $this->getLaunchId()])),
            'class' => 'view',
            'sort_order' => 30,
        ];
    }
}

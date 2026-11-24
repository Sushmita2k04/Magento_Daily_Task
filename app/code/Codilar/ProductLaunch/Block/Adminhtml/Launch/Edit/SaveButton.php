<?php
declare(strict_types=1);

namespace Codilar\ProductLaunch\Block\Adminhtml\Launch\Edit;

use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;

class SaveButton extends GenericButton implements ButtonProviderInterface
{
    public function getButtonData(): array
    {
        return [
            'label' => __('Save Launch'),
            'class' => 'save primary',
            'data_attribute' => [
                'mage-init' => ['buttonAdapter' => ['actions' => [['targetName' => 'productlaunch_launch_form.productlaunch_launch_form', 'actionName' => 'save', 'params' => [false]]]]],
            ],
            'class_name' => \Magento\Ui\Component\Control\Container::SPLIT_BUTTON,
            'options' => [
                [
                    'label' => __('Save & Continue Edit'),
                    'data_attribute' => [
                        'mage-init' => ['buttonAdapter' => ['actions' => [['targetName' => 'productlaunch_launch_form.productlaunch_launch_form', 'actionName' => 'save', 'params' => [true]]]]],
                    ],
                ],
            ],
            'sort_order' => 40,
        ];
    }
}

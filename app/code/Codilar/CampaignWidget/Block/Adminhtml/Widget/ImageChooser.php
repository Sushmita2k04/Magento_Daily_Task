<?php

namespace Codilar\CampaignWidget\Block\Adminhtml\Widget;

use Magento\Backend\Block\Template;
use Magento\Framework\Data\Form\Element\AbstractElement;

class ImageChooser extends Template
{
    public function prepareElementHtml(AbstractElement $element): AbstractElement
    {
        $config = (array) $this->getData('config');

        $sourceUrl = $this->getUrl(
            'cms/wysiwyg_images/index',
            [
                'target_element_id' => $element->getId(),
                'type' => 'file'
            ]
        );

        $button = $this->getLayout()
            ->createBlock(\Magento\Backend\Block\Widget\Button::class)
            ->setType('button')
            ->setClass('btn-chooser')
            ->setLabel(
                $config['button']['open'] ?? __('Choose Image')
            )
            ->setOnClick(
                "MediabrowserUtility.openDialog('" . $sourceUrl . "')"
            );

        $element->setData(
            'after_element_html',
            $button->toHtml()
        );

        return $element;
    }
}
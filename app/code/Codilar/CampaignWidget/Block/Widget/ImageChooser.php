<?php

namespace Codilar\CampaignWidget\Block\Adminhtml\Widget;

use Magento\Backend\Block\Template;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\Data\Form\Element\Factory;

class ImageChooser extends Template
{
    public function __construct(
        Template\Context $context,
        private readonly Factory $elementFactory,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

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

        $chooser = $this->getLayout()
            ->createBlock('Magento\Backend\Block\Widget\Button')
            ->setType('button')
            ->setClass('btn-chooser')
            ->setLabel(
                $config['button']['open'] ?? __('Choose Image')
            )
            ->setOnClick(
                "MediabrowserUtility.openDialog('" . $sourceUrl . "')"
            )
            ->setDisabled($element->getReadonly());

        $input = $this->elementFactory->create(
            'text',
            ['data' => $element->getData()]
        );

        $input->setId($element->getId());
        $input->setForm($element->getForm());
        $input->setClass('widget-option input-text admin__control-text');

        if ($element->getRequired()) {
            $input->addClass('required-entry');
        }

        $input->addCustomAttribute('data-force_static_path', 1);

        $element->setData(
            'after_element_html',
            $input->getElementHtml() . $chooser->toHtml()
        );

        return $element;
    }
}
<?php

namespace Codilar\CampaignWidget\Block\Adminhtml\Widget;

use Magento\Backend\Block\Template;
use Magento\Framework\Data\Form\Element\AbstractElement;

class ImageChooser extends Template
{
    public function prepareElementHtml(AbstractElement $element): string
    {
        $inputId = $element->getHtmlId();

        $html = '<div class="admin__field-control">';
        $html .= '<input type="text"';
        $html .= ' id="' . $inputId . '"';
        $html .= ' name="' . $element->getName() . '"';
        $html .= ' value="' . $element->getEscapedValue() . '"';
        $html .= ' class="admin__control-text"';
        $html .= ' />';

        $html .= '</div>';

        return $html;
    }
}
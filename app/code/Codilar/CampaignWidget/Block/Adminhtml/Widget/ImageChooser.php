<?php

namespace Codilar\CampaignWidget\Block\Adminhtml\Widget;

use Magento\Backend\Block\Template;
use Magento\Framework\Data\Form\Element\AbstractElement;

class ImageChooser extends Template
{
    public function prepareElementHtml(AbstractElement $element): string
    {
        return sprintf(
            '<div class="admin__field-control">
                <input type="text" id="%s" name="%s" value="%s" class="admin__control-text" />
            </div>',
            $element->getHtmlId(),
            $element->getName(),
            $element->getEscapedValue()
        );
    }
}

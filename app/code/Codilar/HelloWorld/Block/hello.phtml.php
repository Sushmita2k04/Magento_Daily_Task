<?php

namespace Codilar\HelloWorld\Block;

use Magento\Framework\View\Element\Template;

class Hello extends Template
{
    public function getMessage()
    {
        return __('Hello World!');
    }
}
?>

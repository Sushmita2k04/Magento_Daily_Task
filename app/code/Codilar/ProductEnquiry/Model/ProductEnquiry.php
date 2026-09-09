<?php

namespace Codilar\ProductEnquiry\Model;

use Magento\Framework\Model\AbstractModel;

class ProductEnquiry extends AbstractModel
{
    protected function _construct()
    {
        $this->_init(
            \Codilar\ProductEnquiry\Model\ResourceModel\ProductEnquiry::class
        );
    }
}

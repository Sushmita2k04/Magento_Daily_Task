<?php

namespace Codilar\ProductEnquiry\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class ProductEnquiry extends AbstractDb
{
    protected function _construct()
    {
        $this->_init(
            'product_enquiry',
            'entity_id'
        );
    }
}

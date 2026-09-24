<?php

namespace Codilar\ProductEnquiry\Model\ResourceModel\ProductEnquiry;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Codilar\ProductEnquiry\Model\ProductEnquiry as ProductEnquiryModel;
use Codilar\ProductEnquiry\Model\ResourceModel\ProductEnquiry as ProductEnquiryResource;

class Collection extends AbstractCollection
{
    protected function _construct()
    {
        $this->_init(
            ProductEnquiryModel::class,
            ProductEnquiryResource::class
        );
    }
}

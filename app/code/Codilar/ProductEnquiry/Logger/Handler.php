<?php

declare(strict_types=1);

namespace Codilar\ProductEnquiry\Logger;

use Magento\Framework\Filesystem\DriverInterface;
use Magento\Framework\Logger\Handler\Base;
use Monolog\Logger;

class Handler extends Base
{
    protected $loggerType = Logger::INFO;

    public function __construct(
        DriverInterface $filesystem
    ) {
        parent::__construct(
            $filesystem,
            BP . '/var/log/product_enquiry.log'
        );
    }
}
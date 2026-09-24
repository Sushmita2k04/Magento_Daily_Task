<?php

declare(strict_types=1);

namespace Codilar\LoginPopup\Model\Logger;

use Monolog\Handler\StreamHandler;
use Monolog\Level;

class Handler extends StreamHandler
{
    public function __construct()
    {
        parent::__construct(
            BP . '/var/log/codilar_custom.log',
            Level::Debug
        );
    }
}

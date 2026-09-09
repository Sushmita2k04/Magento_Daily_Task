<?php

declare(strict_types=1);

namespace Codilar\LoginPopup\Model\Logger;

use Monolog\Logger as MonologLogger;

class Logger extends MonologLogger
{
    public function __construct(
        Handler $handler
    ) {
        parent::__construct(
            'Codilar_login_popup',
            [$handler]
        );
    }
}

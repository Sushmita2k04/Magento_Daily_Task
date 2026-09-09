<?php

declare(strict_types=1);

namespace Codilar\ProductEnquiry\Logger;

class Logger extends \Monolog\Logger
{
    public function __construct(
        string $name,
        array $handlers = [],
        array $processors = []
    ) {
        parent::__construct(
            $name,
            $handlers,
            $processors
        );
    }
}

<?php

declare(strict_types=1);

namespace Codilar\ProductEnquiry\Logger;

use Monolog\Handler\StreamHandler;
use Monolog\Level;

class Handler extends StreamHandler
{
    public function __construct(
        string $stream,
        int|Level $level = Level::Debug,
        bool $bubble = true
    ) {
        $streamResource = fopen($stream, 'a');

        if ($streamResource === false) {
            throw new \RuntimeException(
                sprintf('Unable to open log file: %s', $stream)
            );
        }

        parent::__construct(
            $streamResource,
            $level,
            $bubble
        );
    }
}

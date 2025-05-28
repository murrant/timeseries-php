<?php

namespace TimeSeriesPhp\Exceptions;

class RRDtoolCommandTimeoutException extends RRDtoolException
{
    public function __construct(string $command, array $args = [], string $message = '', int $code = 0, ?\Throwable $previous = null)
    {
        $message = $message ?: "RRDtool Command '$command' timed out";
        parent::__construct($command, $args, $message, $code, $previous);
    }
}

<?php

namespace TimeSeriesPhp\Exceptions\Driver;

class RRDtoolCommandTimeoutException extends RRDtoolException
{
    /**
     * @param  string[]  $args
     */
    public function __construct(string $command, array $args = [], string $message = '', int $code = 0, ?\Throwable $previous = null)
    {
        $message = $message ?: "RRDtool Command '$command' timed out";
        parent::__construct($command, $args, $message, $code, $previous);
    }
}

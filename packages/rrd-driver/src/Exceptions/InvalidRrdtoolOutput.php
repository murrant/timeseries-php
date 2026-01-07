<?php

namespace TimeseriesPhp\Driver\RRD\Exceptions;

class InvalidRrdtoolOutput extends RrdException
{
    public function __construct(
        public readonly string $output,
        public readonly ?string $command = null,
    ) {
        parent::__construct("Invalid RRDtool $command output: $output");
    }
}

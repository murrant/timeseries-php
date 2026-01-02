<?php

namespace TimeseriesPhp\Driver\RRD\Factories;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Process\InputStream;
use TimeseriesPhp\Driver\RRD\RrdConfig;
use TimeseriesPhp\Driver\RRD\RrdProcess;

class RrdProcessFactory
{
    public function make(RrdConfig $config, ?LoggerInterface $logger = null, ?InputStream $input = null): RrdProcess
    {
        return new RrdProcess($config, $logger ?? new NullLogger, $input ?? new InputStream);
    }
}

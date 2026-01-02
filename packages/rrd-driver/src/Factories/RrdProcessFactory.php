<?php

namespace TimeseriesPhp\Driver\RRD\Factories;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Process\InputStream;
use TimeseriesPhp\Driver\RRD\RrdConfig;
use TimeseriesPhp\Driver\RRD\RrdProcess;

readonly class RrdProcessFactory
{
    public function __construct(
        private LoggerInterface $logger = new NullLogger,
        private InputStream $input = new InputStream
    ) {}

    public function make(RrdConfig $config): RrdProcess
    {
        return new RrdProcess($config, $this->logger, $this->input);
    }
}

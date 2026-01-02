<?php

namespace TimeseriesPhp\Driver\RRD\Factories;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Process\InputStream;
use TimeseriesPhp\Driver\RRD\Contracts\RrdtoolInterface;
use TimeseriesPhp\Driver\RRD\RrdConfig;
use TimeseriesPhp\Driver\RRD\RrdtoolCli;

class RrdtoolFactory
{
    public function make(
        RrdConfig $config,
        RrdProcessFactory $processFactory,
        LoggerInterface $logger = new NullLogger,
        InputStream $input = new InputStream): RrdtoolInterface
    {
        return new RrdtoolCli($config, $processFactory, $logger, $input);
    }
}

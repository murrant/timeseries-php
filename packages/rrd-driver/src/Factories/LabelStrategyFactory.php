<?php

namespace TimeseriesPhp\Driver\RRD\Factories;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Process\InputStream;
use TimeseriesPhp\Driver\RRD\Contracts\LabelStrategy;
use TimeseriesPhp\Driver\RRD\FilenameLabelStrategy;
use TimeseriesPhp\Driver\RRD\RrdConfig;

class LabelStrategyFactory
{
    public function make(
        RrdConfig $config,
        ?RrdtoolFactory $factory = null,
        ?RrdProcessFactory $processFactory = null,
        ?LoggerInterface $logger = new NullLogger,
        InputStream $input = new InputStream,
    ): LabelStrategy
    {
        $factory ??= new RrdtoolFactory;
        $processFactory ??= new RrdProcessFactory;

        return new FilenameLabelStrategy($config, $factory->make($config, $processFactory, $logger, $input));
    }
}

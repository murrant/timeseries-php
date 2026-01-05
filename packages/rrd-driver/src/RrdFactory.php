<?php

namespace TimeseriesPhp\Driver\RRD;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use TimeseriesPhp\Core\Contracts\DriverConfig;
use TimeseriesPhp\Core\Contracts\DriverFactory;
use TimeseriesPhp\Core\Contracts\MetricRepository;
use TimeseriesPhp\Core\Contracts\QueryCompiler;
use TimeseriesPhp\Core\Contracts\QueryExecutor;
use TimeseriesPhp\Core\Contracts\Writer;
use TimeseriesPhp\Core\Runtime;
use TimeseriesPhp\Core\Services\DriverServiceRegistry;
use TimeseriesPhp\Driver\RRD\Contracts\LabelStrategy;
use TimeseriesPhp\Driver\RRD\Contracts\RrdtoolInterface;
use TimeseriesPhp\Driver\RRD\Factories\LabelStrategyFactory;
use TimeseriesPhp\Driver\RRD\Factories\RrdProcessFactory;
use TimeseriesPhp\Driver\RRD\Factories\RrdtoolFactory;

class RrdFactory implements DriverFactory
{
    public function __construct(
        private readonly MetricRepository $metricRepository,
        private readonly RrdtoolFactory  $rrdtoolFactory,
        private readonly RrdProcessFactory $rrdProcessFactory,
        private readonly LabelStrategyFactory $labelStrategyFactory,
        private readonly LoggerInterface  $logger = new NullLogger,
    ) {}


    public function make(DriverConfig|array $config): Runtime
    {
        if (is_array($config)) {
            $config = RrdConfig::fromArray($config);
        }

        if (! $config instanceof RrdConfig) {
            throw new \InvalidArgumentException('Invalid configuration provided for RRD driver');
        }

        $labelStrategy = $this->labelStrategyFactory->make($config);
        $rrdtool = $this->rrdtoolFactory->make($config);
        $rrdProcess = $this->rrdProcessFactory->make($config);

        return new Runtime(
            new DriverServiceRegistry([
                Writer::class => fn () => new RrdWriter($config, $this->metricRepository, $rrdtool, $labelStrategy, $this->logger),
                QueryCompiler::class => fn () => new RrdCompiler($config, $this->metricRepository),
                QueryExecutor::class => fn () => new RrdQueryExecutor($rrdProcess, $this->logger),
                MetricRepository::class => $this->metricRepository,
                RrdtoolInterface::class => $rrdtool,
                RrdProcess::class => $rrdProcess,
                LabelStrategy::class => $labelStrategy,
            ]),
            $config
        );
    }
}

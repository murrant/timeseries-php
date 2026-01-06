<?php

declare(strict_types=1);

namespace TimeseriesPhp\Driver\Aggregate;

use TimeseriesPhp\Core\Attributes\TimeseriesPhpDriver;
use TimeseriesPhp\Core\Contracts\DriverConfig;
use TimeseriesPhp\Core\Contracts\DriverFactory;
use TimeseriesPhp\Core\Contracts\MetricRepository;
use TimeseriesPhp\Core\Contracts\Writer;
use TimeseriesPhp\Core\Metrics\Repository\RuntimeMetricRepository;
use TimeseriesPhp\Core\Runtime;
use TimeseriesPhp\Core\Services\DriverServiceRegistry;
use TimeseriesPhp\Core\TimeseriesManager;

#[TimeseriesPhpDriver('aggregate')]
final readonly class AggregateFactory implements DriverFactory
{
    public function __construct(
        private TimeseriesManager $manager,
        private MetricRepository $metricRepository = new RuntimeMetricRepository,
    ) {}

    /**
     * @param  array<string, mixed>|DriverConfig  $config
     */
    public function make(array|DriverConfig $config): Runtime
    {
        $config = AggregateConfig::make($config);

        $services = new DriverServiceRegistry([
            Writer::class => function () use ($config): AggregateWriter {
                $writers = array_map(
                    fn (string $connection) => $this->manager->connection($connection)->writer(),
                    $config->connections
                );

                return new AggregateWriter($writers);
            },
        ]);

        return new Runtime($config, $services, $this->metricRepository);
    }
}

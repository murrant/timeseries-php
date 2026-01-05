<?php

declare(strict_types=1);

namespace TimeseriesPhp\Driver\Null;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use TimeseriesPhp\Core\Contracts\DriverConfig;
use TimeseriesPhp\Core\Contracts\DriverFactory;
use TimeseriesPhp\Core\Contracts\MetricRepository;
use TimeseriesPhp\Core\Contracts\QueryCompiler;
use TimeseriesPhp\Core\Contracts\QueryExecutor;
use TimeseriesPhp\Core\Contracts\Writer;
use TimeseriesPhp\Core\Metrics\Repository\RuntimeMetricRepository;
use TimeseriesPhp\Core\Runtime;
use TimeseriesPhp\Core\Services\DriverServiceRegistry;

final readonly class NullFactory implements DriverFactory
{
    public function __construct(
        private LoggerInterface  $logger = new NullLogger,
        private MetricRepository $metricRepository = new RuntimeMetricRepository(),
    ) {}

    public function make(DriverConfig|array $config): Runtime
    {
        $config = NullConfig::make($config);

        return new Runtime(
            config: $config,
            services: new DriverServiceRegistry([
                Writer::class => fn () => new NullWriter($this->logger),
                QueryCompiler::class => fn () => new NullCompiler($this->logger),
                QueryExecutor::class => fn () => new NullQueryExecutor($this->logger),
            ]),
            metrics: $this->metricRepository
        );
    }
}

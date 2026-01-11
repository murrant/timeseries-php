<?php

namespace TimeseriesPhp\Driver\InfluxDB2;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use PsrDiscovery\Discover;
use TimeseriesPhp\Core\Attributes\TimeseriesPhpDriver;
use TimeseriesPhp\Core\Contracts\DriverConfig;
use TimeseriesPhp\Core\Contracts\DriverFactory;
use TimeseriesPhp\Core\Contracts\LabelDiscovery;
use TimeseriesPhp\Core\Contracts\MetricRepository;
use TimeseriesPhp\Core\Contracts\QueryCompiler;
use TimeseriesPhp\Core\Contracts\QueryExecutor;
use TimeseriesPhp\Core\Contracts\Writer;
use TimeseriesPhp\Core\Metrics\Repository\RuntimeMetricRepository;
use TimeseriesPhp\Core\Runtime;
use TimeseriesPhp\Core\Services\DriverServiceRegistry;
use TimeseriesPhp\Driver\InfluxDB2\Factories\FieldStrategyFactory;

#[TimeseriesPhpDriver('influxdb2')]
class InfluxFactory implements DriverFactory
{
    public function __construct(
        private readonly ?ClientInterface $httpClient = null,
        private readonly ?RequestFactoryInterface $requestFactory = null,
        private readonly ?StreamFactoryInterface $streamFactory = null,
        private readonly ?FieldStrategy $fieldStrategy = null,
        private readonly MetricRepository $metricRepository = new RuntimeMetricRepository,
        private readonly LoggerInterface $logger = new NullLogger,
    ) {}

    public function make(array|DriverConfig $config): Runtime
    {
        if (is_array($config)) {
            $config = InfluxConfig::fromArray($config);
        }

        if (! $config instanceof InfluxConfig) {
            throw new \InvalidArgumentException('Invalid configuration provided for InfluxDB2 driver');
        }

        $httpClient = $this->httpClient ?? Discover::httpClient();
        $requestFactory = $this->requestFactory ?? Discover::httpRequestFactory();
        $streamFactory = $this->streamFactory ?? Discover::httpStreamFactory();
        $fieldStrategy = $this->fieldStrategy ?? new FieldStrategyFactory()->make($config);

        $compiler = new InfluxCompiler(
            $config,
            $this->metricRepository,
            $fieldStrategy,
        );

        $executor = new InfluxQueryExecutor(
            $config,
            $httpClient,
            $requestFactory,
            $streamFactory,
            $this->logger,
        );

        $services = new DriverServiceRegistry([
            Writer::class => fn () => new InfluxWriter(
                $config,
                $httpClient,
                $requestFactory,
                $streamFactory,
                $this->logger,
            ),
            QueryCompiler::class => $compiler,
            QueryExecutor::class => $executor,
            LabelDiscovery::class => new InfluxLabelDiscovery(
                $compiler,
                $executor
            ),
        ]);

        return new Runtime($config, $services, $this->metricRepository);
    }
}

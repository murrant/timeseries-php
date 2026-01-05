<?php

namespace TimeseriesPhp\Core;

use TimeseriesPhp\Core\Contracts\DriverConfig;
use TimeseriesPhp\Core\Contracts\LabelDiscovery;
use TimeseriesPhp\Core\Contracts\MetricRepository;
use TimeseriesPhp\Core\Contracts\QueryCompiler;
use TimeseriesPhp\Core\Contracts\QueryExecutor;
use TimeseriesPhp\Core\Contracts\Writer;
use TimeseriesPhp\Core\Exceptions\UnsupportedServiceException;
use TimeseriesPhp\Core\Metrics\Repository\RuntimeMetricRepository;
use TimeseriesPhp\Core\Services\DriverServiceRegistry;

final readonly class Runtime
{
    public function __construct(
        private DriverServiceRegistry $services,
        public DriverConfig $config,
    ) {}

    /**
     * @throws UnsupportedServiceException
     */
    public function writer(): Writer
    {
        return $this->services->get(Writer::class);
    }

    /**
     * @throws UnsupportedServiceException
     */
    public function compiler(): QueryCompiler
    {
        return $this->services->get(QueryCompiler::class);
    }

    /**
     * @throws UnsupportedServiceException
     */
    public function executor(): QueryExecutor
    {
        return $this->services->get(QueryExecutor::class);
    }

    public function metrics(): MetricRepository
    {
        // FIXME
        return new RuntimeMetricRepository();
    }

    public function labels(): LabelDiscovery
    {
        return $this->services->get(LabelDiscovery::class);
    }
}

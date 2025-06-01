<?php

namespace TimeSeriesPhp\Drivers\InfluxDB\Config;

use TimeSeriesPhp\Core\Attributes\Config;
use TimeSeriesPhp\Core\Config\AbstractConfig;
use TimeSeriesPhp\Drivers\InfluxDB\InfluxDBDriver;

#[Config('influxdb_performance', InfluxDBDriver::class)]
class PerformanceConfig extends AbstractConfig
{
    protected array $defaults = [
        'query_timeout' => 30,
        'batch_size' => 1000,
        'max_concurrent_queries' => 10,
        'enable_query_profiling' => false,
        'slow_query_threshold' => 5.0, // seconds
        'memory_limit' => '128M',
        'enable_metrics' => false,
        'metrics_interval' => 60, // seconds
        'auto_optimize_queries' => false,
    ];

    public function __construct(array $config = [])
    {
        $this->addValidator('query_timeout', fn ($timeout) => is_numeric($timeout) && $timeout > 0);
        $this->addValidator('batch_size', fn ($size) => is_int($size) && $size > 0);
        $this->addValidator('max_concurrent_queries', fn ($max) => is_int($max) && $max > 0);

        parent::__construct($config);
    }

    public function isProfilingEnabled(): bool
    {
        return $this->getBool('enable_query_profiling');
    }

    public function isMetricsEnabled(): bool
    {
        return $this->getBool('enable_metrics');
    }

    public function getMemoryLimitBytes(): int
    {
        $limit = $this->getString('memory_limit');

        return $this->parseMemoryLimit($limit);
    }

    private function parseMemoryLimit(string $limit): int
    {
        $unit = strtoupper(substr($limit, -1));
        $value = (int) substr($limit, 0, -1);

        return match ($unit) {
            'G' => $value * 1024 * 1024 * 1024,
            'M' => $value * 1024 * 1024,
            'K' => $value * 1024,
            default => (int) $limit,
        };
    }
}

<?php

namespace TimeSeriesPhp\Drivers\InfluxDB\Config;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use TimeSeriesPhp\Core\Attributes\Config;
use TimeSeriesPhp\Core\Driver\AbstractDriverConfiguration;
use TimeSeriesPhp\Drivers\InfluxDB\InfluxDBDriver;

/**
 * Configuration for the InfluxDB performance settings
 */
#[Config('influxdb_performance', InfluxDBDriver::class)]
class PerformanceConfig extends AbstractDriverConfiguration
{
    /**
     * @param  int  $query_timeout  Query timeout in seconds
     * @param  int  $batch_size  Maximum number of points to write in a batch
     * @param  int  $max_concurrent_queries  Maximum number of concurrent queries
     * @param  bool  $enable_query_profiling  Whether to enable query profiling
     * @param  float  $slow_query_threshold  Threshold in seconds for slow queries
     * @param  string  $memory_limit  Memory limit for queries
     * @param  bool  $enable_metrics  Whether to enable metrics collection
     * @param  int  $metrics_interval  Interval in seconds for metrics collection
     * @param  bool  $auto_optimize_queries  Whether to automatically optimize queries
     */
    public function __construct(
        public readonly int $query_timeout = 30,
        public readonly int $batch_size = 1000,
        public readonly int $max_concurrent_queries = 10,
        public readonly bool $enable_query_profiling = false,
        public readonly float $slow_query_threshold = 5.0,
        public readonly string $memory_limit = '128M',
        public readonly bool $enable_metrics = false,
        public readonly int $metrics_interval = 60,
        public readonly bool $auto_optimize_queries = false,
    ) {}

    /**
     * Configure the schema for this driver
     *
     * @param  ArrayNodeDefinition  $rootNode  The root node
     */
    protected function configureSchema(ArrayNodeDefinition $rootNode): void
    {
        $rootNode
            ->children()
            ->integerNode('query_timeout')
            ->info('Query timeout in seconds')
            ->defaultValue(30)
            ->min(1)
            ->end()
            ->integerNode('batch_size')
            ->info('Maximum number of points to write in a batch')
            ->defaultValue(1000)
            ->min(1)
            ->end()
            ->integerNode('max_concurrent_queries')
            ->info('Maximum number of concurrent queries')
            ->defaultValue(10)
            ->min(1)
            ->end()
            ->booleanNode('enable_query_profiling')
            ->info('Whether to enable query profiling')
            ->defaultFalse()
            ->end()
            ->floatNode('slow_query_threshold')
            ->info('Threshold in seconds for slow queries')
            ->defaultValue(5.0)
            ->min(0.1)
            ->end()
            ->scalarNode('memory_limit')
            ->info('Memory limit for queries')
            ->defaultValue('128M')
            ->cannotBeEmpty()
            ->end()
            ->booleanNode('enable_metrics')
            ->info('Whether to enable metrics collection')
            ->defaultFalse()
            ->end()
            ->integerNode('metrics_interval')
            ->info('Interval in seconds for metrics collection')
            ->defaultValue(60)
            ->min(1)
            ->end()
            ->booleanNode('auto_optimize_queries')
            ->info('Whether to automatically optimize queries')
            ->defaultFalse()
            ->end()
            ->end();
    }

    /**
     * Check if query profiling is enabled
     */
    public function isProfilingEnabled(): bool
    {
        return $this->enable_query_profiling;
    }

    /**
     * Check if metrics collection is enabled
     */
    public function isMetricsEnabled(): bool
    {
        return $this->enable_metrics;
    }

    /**
     * Get the memory limit in bytes
     */
    public function getMemoryLimitBytes(): int
    {
        return $this->parseMemoryLimit($this->memory_limit);
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

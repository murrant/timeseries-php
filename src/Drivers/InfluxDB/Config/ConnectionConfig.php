<?php

namespace TimeSeriesPhp\Drivers\InfluxDB\Config;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use TimeSeriesPhp\Core\Attributes\Config;
use TimeSeriesPhp\Core\Driver\AbstractDriverConfiguration;
use TimeSeriesPhp\Drivers\InfluxDB\InfluxDBDriver;

/**
 * Configuration for the InfluxDB connection
 */
#[Config('influxdb_connection', InfluxDBDriver::class)]
class ConnectionConfig extends AbstractDriverConfiguration
{
    /**
     * @param  int  $pool_size  Maximum number of connections in the pool
     * @param  int  $max_idle_time  Maximum time in seconds a connection can be idle
     * @param  int  $connection_lifetime  Maximum lifetime of a connection in seconds
     * @param  int  $health_check_interval  Interval in seconds between health checks
     * @param  bool  $reconnect_on_failure  Whether to reconnect on failure
     * @param  array<string, mixed>  $circuit_breaker  Circuit breaker configuration
     */
    public function __construct(
        public readonly int $pool_size = 10,
        public readonly int $max_idle_time = 300,
        public readonly int $connection_lifetime = 3600,
        public readonly int $health_check_interval = 60,
        public readonly bool $reconnect_on_failure = true,
        public readonly array $circuit_breaker = [
            'enabled' => false,
            'failure_threshold' => 5,
            'timeout' => 60,
            'recovery_timeout' => 300,
        ],
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
            ->integerNode('pool_size')
            ->info('Maximum number of connections in the pool')
            ->defaultValue(10)
            ->min(1)
            ->max(100)
            ->end()
            ->integerNode('max_idle_time')
            ->info('Maximum time in seconds a connection can be idle')
            ->defaultValue(300)
            ->min(1)
            ->end()
            ->integerNode('connection_lifetime')
            ->info('Maximum lifetime of a connection in seconds')
            ->defaultValue(3600)
            ->min(1)
            ->end()
            ->integerNode('health_check_interval')
            ->info('Interval in seconds between health checks')
            ->defaultValue(60)
            ->min(1)
            ->end()
            ->booleanNode('reconnect_on_failure')
            ->info('Whether to reconnect on failure')
            ->defaultTrue()
            ->end()
            ->arrayNode('circuit_breaker')
            ->info('Circuit breaker configuration')
            ->addDefaultsIfNotSet()
            ->children()
            ->booleanNode('enabled')
            ->info('Whether the circuit breaker is enabled')
            ->defaultFalse()
            ->end()
            ->integerNode('failure_threshold')
            ->info('Number of failures before opening the circuit')
            ->defaultValue(5)
            ->min(1)
            ->end()
            ->integerNode('timeout')
            ->info('Time in seconds the circuit stays open')
            ->defaultValue(60)
            ->min(1)
            ->end()
            ->integerNode('recovery_timeout')
            ->info('Time in seconds before attempting recovery')
            ->defaultValue(300)
            ->min(1)
            ->end()
            ->end()
            ->end()
            ->end();
    }

    /**
     * @return array<string, bool|float|int|string|null>
     */
    public function getCircuitBreakerConfig(): array
    {
        $typedConfig = [];

        foreach ($this->circuit_breaker as $key => $value) {
            if (is_string($key)) {
                if (is_bool($value) || is_float($value) || is_int($value) || is_string($value) || is_null($value)) {
                    $typedConfig[$key] = $value;
                } else {
                    // For objects with __toString method, convert to string
                    if (is_object($value) && method_exists($value, '__toString')) {
                        $typedConfig[$key] = (string) $value;
                    } else {
                        // For other non-scalar types, use a default string
                        $typedConfig[$key] = 'Object';
                    }
                }
            }
        }

        return $typedConfig;
    }

    /**
     * Check if the circuit breaker is enabled
     */
    public function isCircuitBreakerEnabled(): bool
    {
        return (bool) ($this->circuit_breaker['enabled'] ?? false);
    }
}

<?php

namespace TimeSeriesPhp\Drivers\InfluxDB\Config;

use TimeSeriesPhp\Core\Attributes\Config;
use TimeSeriesPhp\Core\Config\AbstractConfig;
use TimeSeriesPhp\Drivers\InfluxDB\InfluxDBDriver;
use TimeSeriesPhp\Exceptions\Config\ConfigurationException;

#[Config('influxdb_connection', InfluxDBDriver::class)]
class ConnectionConfig extends AbstractConfig
{
    protected array $defaults = [
        'pool_size' => 10,
        'max_idle_time' => 300, // seconds
        'connection_lifetime' => 3600, // seconds
        'health_check_interval' => 60, // seconds
        'reconnect_on_failure' => true,
        'circuit_breaker' => [
            'enabled' => false,
            'failure_threshold' => 5,
            'timeout' => 60,
            'recovery_timeout' => 300,
        ],
    ];

    public function __construct(array $config = [])
    {
        $this->addValidator('pool_size', fn ($size) => is_int($size) && $size > 0 && $size <= 100);
        $this->addValidator('max_idle_time', fn ($time) => is_int($time) && $time > 0);
        $this->addValidator('connection_lifetime', fn ($time) => is_int($time) && $time > 0);
        $this->addValidator('circuit_breaker', fn ($breaker) => is_array($breaker));

        parent::__construct($config);
    }

    /**
     * @return array<string, bool|float|int|string|null>
     *
     * @throws ConfigurationException
     */
    public function getCircuitBreakerConfig(): array
    {
        $config = $this->getArray('circuit_breaker');
        $typedConfig = [];

        foreach ($config as $key => $value) {
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
     * @throws ConfigurationException
     */
    public function isCircuitBreakerEnabled(): bool
    {
        return (bool) ($this->getArray('circuit_breaker')['enabled'] ?? false);
    }
}

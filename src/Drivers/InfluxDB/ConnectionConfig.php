<?php

namespace TimeSeriesPhp\Drivers\InfluxDB;

use TimeSeriesPhp\Config\AbstractDriverConfig;
use TimeSeriesPhp\Exceptions\ConfigurationException;

class ConnectionConfig extends AbstractDriverConfig
{
    protected string $driverName = 'influxdb';

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

        parent::__construct($config);
    }

    /**
     * @return array<string, mixed>
     *
     * @throws ConfigurationException
     */
    public function getCircuitBreakerConfig(): array
    {
        return $this->getArray('circuit_breaker');
    }

    /**
     * @throws ConfigurationException
     */
    public function isCircuitBreakerEnabled(): bool
    {
        return (bool) ($this->getArray('circuit_breaker')['enabled'] ?? false);
    }
}

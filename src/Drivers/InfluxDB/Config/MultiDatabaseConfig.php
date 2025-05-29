<?php

namespace TimeSeriesPhp\Drivers\InfluxDB\Config;

use TimeSeriesPhp\Exceptions\Config\ConfigurationException;
use TimeSeriesPhp\Support\Config\AbstractDriverConfig;

class MultiDatabaseConfig extends AbstractDriverConfig
{
    protected string $driverName = 'influxdb';

    protected array $defaults = [
        'default' => 'primary',
        'connections' => [],
        'load_balancing' => [
            'enabled' => false,
            'strategy' => 'round_robin', // round_robin, random, weighted
            'weights' => [],
        ],
        'failover' => [
            'enabled' => false,
            'max_retries' => 3,
            'retry_delay' => 1000,
        ],
    ];

    protected array $required = ['connections'];

    public function __construct(array $config = [])
    {
        parent::__construct($config);
        $this->validateConnections();
    }

    /**
     * @throws ConfigurationException
     */
    private function validateConnections(): void
    {
        $connections = $this->getArray('connections');

        if (empty($connections)) {
            throw new ConfigurationException('At least one connection must be configured');
        }

        $default = $this->getString('default');
        if ($default && ! isset($connections[$default])) {
            throw new ConfigurationException("Default connection '{$default}' is not defined in connections");
        }

        foreach ($connections as $name => $config) {
            if (! is_array($config) || ! isset($config['driver'])) {
                throw new ConfigurationException("Driver not specified for connection '{$name}'");
            }
        }
    }

    /**
     * @return array<string, string>
     *
     * @throws ConfigurationException
     */
    public function getConnection(string $name): array
    {
        $connections = $this->getArray('connections');

        if (! isset($connections[$name])) {
            throw new ConfigurationException("Connection '{$name}' not found");
        }

        $connection = $connections[$name];

        if (! is_array($connection)) {
            throw new ConfigurationException("Connection '{$name}' is not an array");
        }

        foreach ($connection as $key => $value) {
            if (! is_string($key)) {
                throw new ConfigurationException("Connection '{$name}' has invalid key");
            }

            if (! is_string($value)) {
                throw new ConfigurationException("Connection '{$name}' has invalid value");
            }
        }

        /** @var array<string, string> $connection */
        return $connection;
    }

    /**
     * @return array<string, string>
     *
     * @throws ConfigurationException
     */
    public function getDefaultConnection(): array
    {
        $default = $this->getString('default');

        return $this->getConnection($default);
    }

    /**
     * @return list<int|string>
     *
     * @throws ConfigurationException
     */
    public function getConnectionNames(): array
    {
        return array_keys($this->getArray('connections'));
    }

    /**
     * @param  array<string, string>  $config
     *
     * @throws ConfigurationException
     */
    public function addConnection(string $name, array $config): self
    {
        $connections = $this->getArray('connections');
        $connections[$name] = $config;
        $this->set('connections', $connections);

        return $this;
    }

    /**
     * @throws ConfigurationException
     */
    public function isLoadBalancingEnabled(): bool
    {
        return (bool) ($this->getArray('load_balancing')['enabled'] ?? false);
    }

    /**
     * @throws ConfigurationException
     */
    public function getLoadBalancingStrategy(): string
    {
        $strategy = $this->getArray('load_balancing')['strategy'] ?? 'round_robin';

        if (! is_string($strategy)) {
            throw new ConfigurationException('Load balancing strategy must be a string');
        }

        return $strategy;
    }

    /**
     * @throws ConfigurationException
     */
    public function isFailoverEnabled(): bool
    {
        return (bool) ($this->getArray('failover')['enabled'] ?? false);
    }
}

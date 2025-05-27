<?php

namespace TimeSeriesPhp\Drivers\InfluxDB;

use TimeSeriesPhp\Config\AbstractDriverConfig;
use TimeSeriesPhp\Exceptions\ConfigurationException;

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

    private function validateConnections(): void
    {
        $connections = $this->get('connections', []);

        if (empty($connections)) {
            throw new ConfigurationException('At least one connection must be configured');
        }

        $default = $this->get('default');
        if ($default && ! isset($connections[$default])) {
            throw new ConfigurationException("Default connection '{$default}' is not defined in connections");
        }

        foreach ($connections as $name => $config) {
            if (! isset($config['driver'])) {
                throw new ConfigurationException("Driver not specified for connection '{$name}'");
            }
        }
    }

    public function getConnection(string $name): array
    {
        $connections = $this->get('connections', []);

        if (! isset($connections[$name])) {
            throw new ConfigurationException("Connection '{$name}' not found");
        }

        return $connections[$name];
    }

    public function getDefaultConnection(): array
    {
        $default = $this->get('default');

        return $this->getConnection($default);
    }

    public function getConnectionNames(): array
    {
        return array_keys($this->get('connections', []));
    }

    public function addConnection(string $name, array $config): self
    {
        $connections = $this->get('connections', []);
        $connections[$name] = $config;
        $this->set('connections', $connections);

        return $this;
    }

    public function isLoadBalancingEnabled(): bool
    {
        return $this->get('load_balancing')['enabled'] ?? false;
    }

    public function getLoadBalancingStrategy(): string
    {
        return $this->get('load_balancing')['strategy'] ?? 'round_robin';
    }

    public function isFailoverEnabled(): bool
    {
        return $this->get('failover')['enabled'] ?? false;
    }
}

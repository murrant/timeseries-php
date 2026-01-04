<?php

declare(strict_types=1);

namespace TimeseriesPhp\Core;

use TimeseriesPhp\Core\Contracts\DriverConfig;
use TimeseriesPhp\Core\Contracts\DriverFactory;
use TimeseriesPhp\Core\Exceptions\DriverNotFoundException;
use TimeseriesPhp\Core\Exceptions\TimeseriesException;

final class TimeseriesManager
{
    private ?string $default = null;

    /** @var array<string, DriverFactory> */
    private array $factories = [];

    /** @var array<string, Runtime> */
    private array $runtimes = [];

    /** @var array<string, array{driver: string, config: array|DriverConfig}> */
    private array $connections = [];

    public function __construct(array $config = [])
    {
        $this->connections = $config['connections'] ?? [];
    }

    public function registerDriver(string $driverName, DriverFactory $factory): void
    {
        $this->factories[$driverName] = $factory;
    }

    public function addConnection(string $name, string $driverName, array|DriverConfig $config): void
    {
        $this->connections[$name] = ['driver' => $driverName, 'config' => $config,];

        // set default as the first registered connection
        if ($this->default === null) {
            $this->default = $name;
        }

        // Clear any cached runtime for this connection
        unset($this->runtimes[$name]);
    }

    public function hasConnection(string $name): bool
    {
        return isset($this->connections[$name]);
    }

    public function removeConnection(string $name): void
    {
        unset($this->connections[$name], $this->runtimes[$name]);
    }

    public function setDefaultConnection(string $name): void
    {
        $this->default = $name;
    }

    public function connection(?string $name = null): Runtime
    {
        $name ??= $this->default ?? 'default';

        if (isset($this->runtimes[$name])) {
            return $this->runtimes[$name];
        }

        $connectionConfig = $this->connections[$name] ?? null;
        if (!$connectionConfig) {
            throw new TimeseriesException("Connection [$name] not configured.");
        }

        $driverName = $connectionConfig['driver'];
        $driverConfig = $connectionConfig['config'];

        if (! isset($this->factories[$driverName])) {
            throw new DriverNotFoundException("Driver [$driverName] is not registered.");
        }

        return $this->runtimes[$name] = $this->factories[$driverName]->make($driverConfig);
    }
}

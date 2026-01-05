<?php

namespace TimeseriesPhp\Core\Services;

use TimeseriesPhp\Core\Contracts\DriverConfig;
use TimeseriesPhp\Core\Exceptions\UnsupportedServiceException;

/**
 * Driver-scoped service registry with lazy resolution.
 * Acts as a bounded capability container, not a general DI container.
 */
final class DriverServiceRegistry
{
    /** @var array<string, object> Cache of resolved services */
    private array $resolved = [];

    /**
     * @param array<string, object|callable> $services
     */
    public function __construct(
        private readonly array $services = []
    ) {
        foreach ($this->services as $interface => $service) {
            if ($interface === DriverConfig::class) {
                throw new \LogicException('DriverConfig must not be registered as a service');
            }
        }

    }

    /**
     * @template T of object
     * @param class-string<T> $interface
     * @return T
     * @throws UnsupportedServiceException
     */
    public function get(string $interface): object
    {
        if (isset($this->resolved[$interface])) {
            return $this->resolved[$interface];
        }

        if (!isset($this->services[$interface])) {
            throw new UnsupportedServiceException("This driver does not support: {$interface}");
        }

        $service = $this->services[$interface];

        // Lazy Load (If it's a factory closure, run it now)
        if (is_callable($service)) {
            $service = $service();
        }

        $this->resolved[$interface] = $service;

        return $service;
    }

    public function has(string $interface): bool
    {
        return isset($this->services[$interface]);
    }
}

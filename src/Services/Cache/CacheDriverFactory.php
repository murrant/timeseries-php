<?php

declare(strict_types=1);

namespace TimeSeriesPhp\Services\Cache;

use Symfony\Component\DependencyInjection\ContainerInterface;
use TimeSeriesPhp\Contracts\Cache\CacheDriverInterface;
use TimeSeriesPhp\Exceptions\TSDBException;

/**
 * Factory for creating cache driver instances
 */
class CacheDriverFactory
{
    /**
     * @var ContainerInterface The service container
     */
    private ContainerInterface $container;

    /**
     * Create a new CacheDriverFactory instance
     *
     * @param  ContainerInterface  $container  The service container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * Create a cache driver instance based on the configuration
     *
     * @param  array<string, mixed>  $config  The cache configuration
     * @return CacheDriverInterface The cache driver instance
     *
     * @throws TSDBException If the specified driver is not supported
     */
    public function createDriver(array $config): CacheDriverInterface
    {
        /** @var string $driver */
        $driver = $config['driver'] ?? 'file';
        /** @var array<string, mixed> $driverConfig */
        $driverConfig = $config[$driver] ?? [];

        return match ($driver) {
            'array' => new ArrayCacheDriver,
            'file' => new FileCacheDriver($driverConfig),
            default => $this->resolveCustomDriver($driver, $driverConfig),
        };
    }

    /**
     * Resolve a custom cache driver from the container
     *
     * @param  string  $driver  The driver name
     * @param  array<string, mixed>  $config  The driver configuration
     * @return CacheDriverInterface The cache driver instance
     *
     * @throws TSDBException If the driver is not found in the container
     */
    private function resolveCustomDriver(string $driver, array $config): CacheDriverInterface
    {
        $serviceId = 'cache.driver.'.$driver;

        if (! $this->container->has($serviceId)) {
            throw new TSDBException("Unsupported cache driver: {$driver}");
        }

        $driverInstance = $this->container->get($serviceId);

        if (! $driverInstance instanceof CacheDriverInterface) {
            throw new TSDBException("Cache driver service '{$serviceId}' must implement CacheDriverInterface");
        }

        // If the driver is configurable, pass the configuration
        if (method_exists($driverInstance, 'configure')) {
            $driverInstance->configure($config);
        }

        return $driverInstance;
    }
}

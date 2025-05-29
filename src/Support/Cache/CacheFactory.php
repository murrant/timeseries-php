<?php

namespace TimeSeriesPhp\Support\Cache;

use Psr\SimpleCache\CacheInterface;
use TimeSeriesPhp\Exceptions\TSDBException;
use TimeSeriesPhp\Support\Config\CacheConfig;

/**
 * Cache factory that creates the appropriate cache implementation based on the driver configuration.
 * Supports file and memory drivers. Default driver is memory.
 */
class CacheFactory
{
    /**
     * Create a new cache implementation with the given configuration
     */
    public static function create(CacheConfig $config): CacheInterface
    {
        $driver = $config->getString('driver');

        return match ($driver) {
            'memory' => new MemoryCache($config),
            'file' => new FileCache($config),
            default => throw new TSDBException("Unsupported cache driver: {$driver}"),
        };
    }

    /**
     * Create a new cache implementation with default configuration
     */
    public static function make(): CacheInterface
    {
        return self::create(new CacheConfig([
            'enabled' => true,
            'driver' => 'memory',
        ]));
    }
}

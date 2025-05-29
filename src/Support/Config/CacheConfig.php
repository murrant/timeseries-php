<?php

namespace TimeSeriesPhp\Support\Config;

use TimeSeriesPhp\Exceptions\ConfigurationException;

class CacheConfig extends AbstractConfig
{
    /**
     * @var array<string, mixed>
     */
    protected array $defaults = [
        'enabled' => false,
        'driver' => 'memory', // memory, redis, memcached, file
        'ttl' => 300, // seconds
        'prefix' => 'tsdb_cache',
        'serialization' => 'php', // php, json, msgpack
        'compression' => false,
        'memory' => [
            // No specific configuration needed for memory driver
        ],
        'redis' => [
            'host' => 'localhost',
            'port' => 6379,
            'database' => 0,
        ],
        'memcached' => [
            'servers' => [['localhost', 11211]],
        ],
        'file' => [
            'path' => '/tmp/tsdb_cache',
            'permissions' => 0755,
        ],
    ];

    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(array $config = [])
    {
        $this->addValidator('ttl', fn ($ttl) => is_int($ttl) && $ttl > 0);
        $this->addValidator('driver', fn ($driver) => in_array($driver, ['memory', 'redis', 'memcached', 'file']));

        parent::__construct($config);
    }

    public function isEnabled(): bool
    {
        return $this->getBool('enabled');
    }

    /**
     * @return array<mixed, mixed>
     *
     * @throws ConfigurationException
     */
    public function getDriverConfig(?string $driver = null): array
    {
        if (empty($driver)) {
            $driver = $this->getString('driver');
        }

        return $this->getArray($driver);
    }
}

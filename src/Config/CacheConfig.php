<?php

namespace TimeSeriesPhp\Config;

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
        return $this->get('enabled', false);
    }

    /**
     * @return array<string, mixed>
     */
    public function getDriverConfig(?string $driver = null): array
    {
        $driver = $driver ?: $this->get('driver');

        return $this->get($driver, []);
    }
}

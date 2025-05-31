<?php

declare(strict_types=1);

namespace TimeSeriesPhp\Services\Cache;

use TimeSeriesPhp\Contracts\Cache\CacheDriverInterface;
use TimeSeriesPhp\Core\Attributes\CacheDriver;

/**
 * Array-based cache driver implementation
 */
#[CacheDriver(name: 'array')]
class ArrayCacheDriver implements CacheDriverInterface
{
    /**
     * @var array<string, array{value: mixed, expires: int|null}> In-memory cache storage
     */
    private array $cache = [];

    /**
     * Create a new ArrayCacheDriver instance
     *
     * @param  array  $config  Driver-specific configuration
     */
    public function __construct(array $config = [])
    {
        // Array driver doesn't need any specific configuration
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $key, mixed $default = null): mixed
    {
        if (! isset($this->cache[$key])) {
            return $default;
        }

        $item = $this->cache[$key];

        // Check if the item has expired
        if ($item['expires'] !== null && $item['expires'] < time()) {
            unset($this->cache[$key]);

            return $default;
        }

        return $item['value'];
    }

    /**
     * {@inheritdoc}
     */
    public function set(string $key, mixed $value, ?int $ttl = null): bool
    {
        $expires = $ttl !== null ? time() + $ttl : null;

        $this->cache[$key] = [
            'value' => $value,
            'expires' => $expires,
        ];

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function delete(string $key): bool
    {
        unset($this->cache[$key]);

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function clear(): bool
    {
        $this->cache = [];

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function has(string $key): bool
    {
        if (! isset($this->cache[$key])) {
            return false;
        }

        $item = $this->cache[$key];

        // Check if the item has expired
        if ($item['expires'] !== null && $item['expires'] < time()) {
            unset($this->cache[$key]);

            return false;
        }

        return true;
    }
}

<?php

declare(strict_types=1);

namespace TimeSeriesPhp\Contracts\Cache;

/**
 * Interface for cache drivers
 */
interface CacheDriverInterface
{
    /**
     * Fetches a value from the cache.
     *
     * @param  string  $key  The unique key of this item in the cache.
     * @param  mixed  $default  Default value to return if the key does not exist.
     * @return mixed The value of the item from the cache, or $default in case of cache miss.
     */
    public function get(string $key, mixed $default = null): mixed;

    /**
     * Persists data in the cache, uniquely referenced by a key.
     *
     * @param  string  $key  The key of the item to store.
     * @param  mixed  $value  The value of the item to store, must be serializable.
     * @param  int|null  $ttl  Optional. The TTL value of this item in seconds.
     * @return bool True on success and false on failure.
     */
    public function set(string $key, mixed $value, ?int $ttl = null): bool;

    /**
     * Delete an item from the cache by its unique key.
     *
     * @param  string  $key  The unique cache key of the item to delete.
     * @return bool True if the item was successfully removed. False if there was an error.
     */
    public function delete(string $key): bool;

    /**
     * Wipes clean the entire cache.
     *
     * @return bool True on success and false on failure.
     */
    public function clear(): bool;

    /**
     * Determines whether an item is present in the cache.
     *
     * @param  string  $key  The cache item key.
     */
    public function has(string $key): bool;
}

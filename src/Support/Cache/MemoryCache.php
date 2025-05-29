<?php

namespace TimeSeriesPhp\Support\Cache;

/**
 * Memory-based cache implementation
 */
class MemoryCache extends AbstractCache
{
    /** @var array<string, mixed> */
    protected array $memoryCache = [];

    /**
     * Fetches a value from the cache.
     *
     * @param  string  $key  The unique key of this item in the cache.
     * @param  mixed  $default  Default value to return if the key does not exist.
     * @return mixed The value of the item from the cache, or $default in case of cache miss.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        if (! $this->enabled) {
            return $default;
        }

        return $this->memoryCache[$key] ?? $default;
    }

    /**
     * Persists data in the cache, uniquely referenced by a key.
     *
     * @param  string  $key  The key of the item to store.
     * @param  mixed  $value  The value of the item to store, must be serializable.
     * @param  null|int|\DateInterval  $ttl  Optional. The TTL value of this item.
     * @return bool True on success and false on failure.
     */
    public function set(string $key, mixed $value, $ttl = null): bool
    {
        if (! $this->enabled) {
            return false;
        }

        $this->memoryCache[$key] = $value;

        return true;
    }

    /**
     * Delete an item from the cache by its unique key.
     *
     * @param  string  $key  The unique cache key of the item to delete.
     * @return bool True if the item was successfully removed. False if there was an error.
     */
    public function delete(string $key): bool
    {
        if (! $this->enabled) {
            return false;
        }

        unset($this->memoryCache[$key]);

        return true;
    }

    /**
     * Wipes clean the entire cache's keys.
     *
     * @return bool True on success and false on failure.
     */
    public function clear(): bool
    {
        if (! $this->enabled) {
            return false;
        }

        $this->memoryCache = [];

        return true;
    }

    /**
     * Determines whether an item is present in the cache.
     *
     * @param  string  $key  The cache item key.
     */
    public function has(string $key): bool
    {
        if (! $this->enabled) {
            return false;
        }

        return isset($this->memoryCache[$key]);
    }
}

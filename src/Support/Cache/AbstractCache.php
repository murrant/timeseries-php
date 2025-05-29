<?php

namespace TimeSeriesPhp\Support\Cache;

use Psr\SimpleCache\CacheInterface;
use TimeSeriesPhp\Config\CacheConfig;
use TimeSeriesPhp\Exceptions\TSDBException;

/**
 * Abstract base class for cache implementations
 */
abstract class AbstractCache implements CacheInterface
{
    protected string $prefix;

    protected bool $enabled;

    /**
     * Create a new Cache instance with the given configuration
     */
    public function __construct(CacheConfig $config)
    {
        $this->enabled = $config->isEnabled();
        $this->prefix = $config->getString('prefix');
    }

    /**
     * Fetches a value from the cache.
     *
     * @param  string  $key  The unique key of this item in the cache.
     * @param  mixed  $default  Default value to return if the key does not exist.
     * @return mixed The value of the item from the cache, or $default in case of cache miss.
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException
     *                                                   MUST be thrown if the $key string is not a legal value.
     */
    abstract public function get(string $key, mixed $default = null): mixed;

    /**
     * Persists data in the cache, uniquely referenced by a key.
     *
     * @param  string  $key  The key of the item to store.
     * @param  mixed  $value  The value of the item to store, must be serializable.
     * @param  null|int|\DateInterval  $ttl  Optional. The TTL value of this item.
     * @return bool True on success and false on failure.
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException
     *                                                   MUST be thrown if the $key string is not a legal value.
     */
    abstract public function set(string $key, mixed $value, $ttl = null): bool;

    /**
     * Delete an item from the cache by its unique key.
     *
     * @param  string  $key  The unique cache key of the item to delete.
     * @return bool True if the item was successfully removed. False if there was an error.
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException
     *                                                   MUST be thrown if the $key string is not a legal value.
     */
    abstract public function delete(string $key): bool;

    /**
     * Wipes clean the entire cache's keys.
     *
     * @return bool True on success and false on failure.
     */
    abstract public function clear(): bool;

    /**
     * Determines whether an item is present in the cache.
     *
     * @param  string  $key  The cache item key.
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException
     *                                                   MUST be thrown if the $key string is not a legal value.
     */
    abstract public function has(string $key): bool;

    /**
     * Obtains multiple cache items by their unique keys.
     *
     * @param  iterable<string>  $keys  A list of keys that can be obtained in a single operation.
     * @param  mixed  $default  Default value to return for keys that do not exist.
     * @return array<string, mixed> A list of key => value pairs. Cache keys that do not exist or are stale will have $default as value.
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException
     *                                                   MUST be thrown if $keys is neither an array nor a Traversable,
     *                                                   or if any of the $keys are not a legal value.
     */
    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        $result = [];
        foreach ($keys as $key) {
            /** @phpstan-ignore-next-line */
            if (! is_string($key)) {
                throw new TSDBException('Cache key must be a string');
            }
            $result[$key] = $this->get($key, $default);
        }

        return $result;
    }

    /**
     * Persists a set of key => value pairs in the cache.
     *
     * @param  iterable<string, mixed>  $values  A list of key => value pairs for a multiple-set operation.
     * @param  null|int|\DateInterval  $ttl  Optional. The TTL value of this item.
     * @return bool True on success and false on failure.
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException
     *                                                   MUST be thrown if $values is neither an array nor a Traversable,
     *                                                   or if any of the $values are not a legal value.
     */
    public function setMultiple(iterable $values, $ttl = null): bool
    {
        $success = true;
        foreach ($values as $key => $value) {
            /** @phpstan-ignore-next-line */
            if (! is_string($key)) {
                throw new TSDBException('Cache key must be a string');
            }
            if (! $this->set($key, $value, $ttl)) {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * Deletes multiple cache items in a single operation.
     *
     * @param  iterable<string>  $keys  A list of string-based keys to be deleted.
     * @return bool True if the items were successfully removed. False if there was an error.
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException
     *                                                   MUST be thrown if $keys is neither an array nor a Traversable,
     *                                                   or if any of the $keys are not a legal value.
     */
    public function deleteMultiple(iterable $keys): bool
    {
        $success = true;
        foreach ($keys as $key) {
            /** @phpstan-ignore-next-line */
            if (! is_string($key)) {
                throw new TSDBException('Cache key must be a string');
            }
            if (! $this->delete($key)) {
                $success = false;
            }
        }

        return $success;
    }
}

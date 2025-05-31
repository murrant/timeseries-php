<?php

declare(strict_types=1);

namespace TimeSeriesPhp\Services;

use Psr\SimpleCache\CacheInterface;
use SebastianBergmann\Diff\ConfigurationException;
use TimeSeriesPhp\Exceptions\TSDBException;

/**
 * PSR-16 compatible simple cache implementation
 */
class Cache implements CacheInterface
{
    /**
     * @var bool Whether caching is enabled
     */
    private bool $enabled;

    /**
     * @var int Default TTL in seconds
     */
    private int $defaultTtl;

    /**
     * @var string Cache driver (file, array, redis, memcached)
     */
    private string $driver;

    /**
     * @var string Cache key prefix
     */
    private string $prefix;

    /**
     * @var array Driver-specific configuration
     */
    private array $driverConfig;

    /**
     * @var array In-memory cache for the array driver
     */
    private array $memoryCache = [];

    /**
     * Create a new Cache instance
     *
     * @param  array  $config  The cache configuration
     */
    public function __construct(array $config)
    {
        $this->enabled = $config['enabled'] ?? true;
        $this->defaultTtl = $config['ttl'] ?? 3600;
        $this->driver = $config['driver'] ?? 'file';
        $this->prefix = $config['prefix'] ?? 'tsdb_';
        $this->driverConfig = $config[$this->driver] ?? [];
    }

    /**
     * Fetches a value from the cache.
     *
     * @param  string  $key  The unique key of this item in the cache.
     * @param  mixed  $default  Default value to return if the key does not exist.
     * @return mixed The value of the item from the cache, or $default in case of cache miss.
     *
     * @throws TSDBException If the cache is not available.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        if (! $this->enabled) {
            return $default;
        }

        $key = $this->prefixKey($key);

        try {
            switch ($this->driver) {
                case 'array':
                    return $this->getFromArray($key, $default);
                case 'file':
                    return $this->getFromFile($key, $default);
                default:
                    throw new ConfigurationException("Unsupported cache driver: {$this->driver}");
            }
        } catch (\Exception $e) {
            if ($e instanceof TSDBException) {
                throw $e;
            }
            throw new TSDBException("Cache error: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Persists data in the cache, uniquely referenced by a key.
     *
     * @param  string  $key  The key of the item to store.
     * @param  mixed  $value  The value of the item to store, must be serializable.
     * @param  null|int|\DateInterval  $ttl  Optional. The TTL value of this item.
     * @return bool True on success and false on failure.
     *
     * @throws TSDBException If the cache is not available.
     */
    public function set(string $key, mixed $value, null|int|\DateInterval $ttl = null): bool
    {
        if (! $this->enabled) {
            return true;
        }

        $key = $this->prefixKey($key);
        $ttl = $this->normalizeTtl($ttl);

        try {
            switch ($this->driver) {
                case 'array':
                    return $this->setInArray($key, $value, $ttl);
                case 'file':
                    return $this->setInFile($key, $value, $ttl);
                default:
                    throw new ConfigurationException("Unsupported cache driver: {$this->driver}");
            }
        } catch (\Exception $e) {
            if ($e instanceof TSDBException) {
                throw $e;
            }
            throw new TSDBException("Cache error: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Delete an item from the cache by its unique key.
     *
     * @param  string  $key  The unique cache key of the item to delete.
     * @return bool True if the item was successfully removed. False if there was an error.
     *
     * @throws TSDBException If the cache is not available.
     */
    public function delete(string $key): bool
    {
        if (! $this->enabled) {
            return true;
        }

        $key = $this->prefixKey($key);

        try {
            switch ($this->driver) {
                case 'array':
                    return $this->deleteFromArray($key);
                case 'file':
                    return $this->deleteFromFile($key);
                default:
                    throw new ConfigurationException("Unsupported cache driver: {$this->driver}");
            }
        } catch (\Exception $e) {
            if ($e instanceof TSDBException) {
                throw $e;
            }
            throw new TSDBException("Cache error: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Wipes clean the entire cache.
     *
     * @return bool True on success and false on failure.
     *
     * @throws TSDBException If the cache is not available.
     */
    public function clear(): bool
    {
        if (! $this->enabled) {
            return true;
        }

        try {
            switch ($this->driver) {
                case 'array':
                    return $this->clearArray();
                case 'file':
                    return $this->clearFile();
                default:
                    throw new ConfigurationException("Unsupported cache driver: {$this->driver}");
            }
        } catch (\Exception $e) {
            if ($e instanceof TSDBException) {
                throw $e;
            }
            throw new TSDBException("Cache error: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Obtains multiple cache items by their unique keys.
     *
     * @param  iterable  $keys  A list of keys that can be obtained in a single operation.
     * @param  mixed  $default  Default value to return for keys that do not exist.
     * @return iterable A list of key => value pairs.
     *
     * @throws TSDBException If the cache is not available.
     */
    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        $result = [];
        foreach ($keys as $key) {
            $result[$key] = $this->get($key, $default);
        }

        return $result;
    }

    /**
     * Persists a set of key => value pairs in the cache.
     *
     * @param  iterable  $values  A list of key => value pairs for a multiple-set operation.
     * @param  null|int|\DateInterval  $ttl  Optional. TTL value.
     * @return bool True on success and false on failure.
     *
     * @throws TSDBException If the cache is not available.
     */
    public function setMultiple(iterable $values, null|int|\DateInterval $ttl = null): bool
    {
        $success = true;
        foreach ($values as $key => $value) {
            $success = $this->set($key, $value, $ttl) && $success;
        }

        return $success;
    }

    /**
     * Deletes multiple cache items in a single operation.
     *
     * @param  iterable  $keys  A list of string-based keys to be deleted.
     * @return bool True if the items were successfully removed. False if there was an error.
     *
     * @throws TSDBException If the cache is not available.
     */
    public function deleteMultiple(iterable $keys): bool
    {
        $success = true;
        foreach ($keys as $key) {
            $success = $this->delete($key) && $success;
        }

        return $success;
    }

    /**
     * Determines whether an item is present in the cache.
     *
     * @param  string  $key  The cache item key.
     *
     * @throws TSDBException If the cache is not available.
     */
    public function has(string $key): bool
    {
        if (! $this->enabled) {
            return false;
        }

        $key = $this->prefixKey($key);

        try {
            switch ($this->driver) {
                case 'array':
                    return $this->hasInArray($key);
                case 'file':
                    return $this->hasInFile($key);
                default:
                    throw new ConfigurationException("Unsupported cache driver: {$this->driver}");
            }
        } catch (\Exception $e) {
            if ($e instanceof TSDBException) {
                throw $e;
            }
            throw new TSDBException("Cache error: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Prefix a cache key
     *
     * @param  string  $key  The cache key
     * @return string The prefixed cache key
     */
    private function prefixKey(string $key): string
    {
        return $this->prefix.$key;
    }

    /**
     * Normalize TTL value
     *
     * @param  null|int|\DateInterval  $ttl  The TTL value
     * @return int|null The normalized TTL value in seconds
     */
    private function normalizeTtl(null|int|\DateInterval $ttl): ?int
    {
        if ($ttl === null) {
            return $this->defaultTtl;
        }

        if (is_int($ttl)) {
            return $ttl;
        }

        // Convert DateInterval to seconds
        return (int) $ttl->format('%s');
    }

    // Array driver methods

    private function getFromArray(string $key, mixed $default): mixed
    {
        if (! isset($this->memoryCache[$key])) {
            return $default;
        }

        $item = $this->memoryCache[$key];

        // Check if the item has expired
        if ($item['expires'] !== null && $item['expires'] < time()) {
            unset($this->memoryCache[$key]);

            return $default;
        }

        return $item['value'];
    }

    private function setInArray(string $key, mixed $value, ?int $ttl): bool
    {
        $expires = $ttl !== null ? time() + $ttl : null;

        $this->memoryCache[$key] = [
            'value' => $value,
            'expires' => $expires,
        ];

        return true;
    }

    private function deleteFromArray(string $key): bool
    {
        unset($this->memoryCache[$key]);

        return true;
    }

    private function clearArray(): bool
    {
        $this->memoryCache = [];

        return true;
    }

    private function hasInArray(string $key): bool
    {
        if (! isset($this->memoryCache[$key])) {
            return false;
        }

        $item = $this->memoryCache[$key];

        // Check if the item has expired
        if ($item['expires'] !== null && $item['expires'] < time()) {
            unset($this->memoryCache[$key]);

            return false;
        }

        return true;
    }

    // File driver methods

    private function getFromFile(string $key, mixed $default): mixed
    {
        $path = $this->getFilePath($key);

        if (! file_exists($path)) {
            return $default;
        }

        $data = $this->readCacheFile($path);

        if ($data === null || ($data['expires'] !== null && $data['expires'] < time())) {
            @unlink($path);

            return $default;
        }

        return $data['value'];
    }

    private function setInFile(string $key, mixed $value, ?int $ttl): bool
    {
        $path = $this->getFilePath($key);
        $dir = dirname($path);

        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $expires = $ttl !== null ? time() + $ttl : null;

        $data = [
            'value' => $value,
            'expires' => $expires,
        ];

        return file_put_contents($path, serialize($data), LOCK_EX) !== false;
    }

    private function deleteFromFile(string $key): bool
    {
        $path = $this->getFilePath($key);

        if (file_exists($path)) {
            return @unlink($path);
        }

        return true;
    }

    private function clearFile(): bool
    {
        $directory = $this->driverConfig['directory'] ?? sys_get_temp_dir().'/tsdb_cache';

        if (! is_dir($directory)) {
            return true;
        }

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($files as $file) {
            if ($file->isDir()) {
                rmdir($file->getRealPath());
            } else {
                unlink($file->getRealPath());
            }
        }

        return true;
    }

    private function hasInFile(string $key): bool
    {
        $path = $this->getFilePath($key);

        if (! file_exists($path)) {
            return false;
        }

        $data = $this->readCacheFile($path);

        if ($data === null || ($data['expires'] !== null && $data['expires'] < time())) {
            @unlink($path);

            return false;
        }

        return true;
    }

    private function getFilePath(string $key): string
    {
        $directory = $this->driverConfig['directory'] ?? sys_get_temp_dir().'/tsdb_cache';
        $hash = md5($key);

        // Use the first two characters as a directory to avoid too many files in one directory
        $path = $directory.'/'.substr($hash, 0, 2);

        return $path.'/'.$hash;
    }

    private function readCacheFile(string $path): ?array
    {
        $content = @file_get_contents($path);

        if ($content === false) {
            return null;
        }

        try {
            $data = unserialize($content);

            return is_array($data) ? $data : null;
        } catch (\Exception $e) {
            return null;
        }
    }
}

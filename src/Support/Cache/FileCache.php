<?php

namespace TimeSeriesPhp\Support\Cache;

use TimeSeriesPhp\Support\Config\CacheConfig;

/**
 * File-based cache implementation
 */
class FileCache extends AbstractCache
{
    protected readonly string $path;

    protected readonly int $permissions;

    /**
     * Create a new FileCache instance with the given configuration
     */
    public function __construct(CacheConfig $config)
    {
        parent::__construct($config);

        $this->path = $config->getString('file.path');
        $this->permissions = $config->getInt('file.permissions', 0755);
    }

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

        return $this->getFromFile($key, $default);
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

        return $this->setToFile($key, $value);
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

        return $this->deleteFromFile($key);
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

        return $this->clearFiles();
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

        return $this->fileExists($key);
    }

    /**
     * Get the cache file path for a key
     */
    protected function getCacheFilePath(string $key): string
    {
        $filename = md5($this->prefix.$key);

        return $this->path.'/'.$filename;
    }

    /**
     * Get a value from the file cache
     */
    protected function getFromFile(string $key, mixed $default): mixed
    {
        $filePath = $this->getCacheFilePath($key);
        if (! file_exists($filePath)) {
            return $default;
        }

        $content = file_get_contents($filePath);
        if ($content === false) {
            return $default;
        }

        $data = unserialize($content);
        if ($data === false) {
            return $default;
        }

        return $data;
    }

    /**
     * Set a value to the file cache
     */
    protected function setToFile(string $key, mixed $value): bool
    {
        $filePath = $this->getCacheFilePath($key);
        $directory = dirname($filePath);

        if (! is_dir($directory)) {
            if (! mkdir($directory, $this->permissions, true)) {
                return false;
            }
        }

        $content = serialize($value);

        return file_put_contents($filePath, $content) !== false;
    }

    /**
     * Delete a value from the file cache
     */
    protected function deleteFromFile(string $key): bool
    {
        $filePath = $this->getCacheFilePath($key);
        if (! file_exists($filePath)) {
            return true;
        }

        return unlink($filePath);
    }

    /**
     * Check if a file exists in the cache
     */
    protected function fileExists(string $key): bool
    {
        $filePath = $this->getCacheFilePath($key);

        return file_exists($filePath);
    }

    /**
     * Clear all files from the cache directory
     */
    protected function clearFiles(): bool
    {
        if (! is_dir($this->path)) {
            return true;
        }

        $files = glob($this->path.'/*');
        if ($files === false) {
            return false;
        }

        $success = true;
        foreach ($files as $file) {
            if (is_file($file)) {
                if (! unlink($file)) {
                    $success = false;
                }
            }
        }

        return $success;
    }
}

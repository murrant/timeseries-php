<?php

declare(strict_types=1);

namespace TimeSeriesPhp\Services\Cache;

use TimeSeriesPhp\Contracts\Cache\CacheDriverInterface;
use TimeSeriesPhp\Core\Attributes\CacheDriver;
use TimeSeriesPhp\Utils\Convert;

/**
 * File-based cache driver implementation
 */
#[CacheDriver(name: 'file')]
class FileCacheDriver implements CacheDriverInterface
{
    /**
     * @var string Directory to store cache files
     */
    private string $directory;

    /**
     * Create a new FileCacheDriver instance
     *
     * @param  array<string, mixed>  $config  Driver-specific configuration
     */
    public function __construct(array $config)
    {
        $this->directory = Convert::toString($config['directory'] ?? sys_get_temp_dir().'/tsdb_cache');

        // Ensure the cache directory exists
        if (! is_dir($this->directory)) {
            mkdir($this->directory, 0755, true);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $key, mixed $default = null): mixed
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

    /**
     * {@inheritdoc}
     */
    public function set(string $key, mixed $value, ?int $ttl = null): bool
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

    /**
     * {@inheritdoc}
     */
    public function delete(string $key): bool
    {
        $path = $this->getFilePath($key);

        if (file_exists($path)) {
            return @unlink($path);
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function clear(): bool
    {
        if (! is_dir($this->directory)) {
            return true;
        }

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->directory, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($files as $file) {
            // Ensure $file is a SplFileInfo object
            if (! $file instanceof \SplFileInfo) {
                continue;
            }

            if ($file->isDir()) {
                $path = $file->getRealPath();
                if (is_string($path)) {
                    rmdir($path);
                }
            } else {
                $path = $file->getRealPath();
                if (is_string($path)) {
                    unlink($path);
                }
            }
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function has(string $key): bool
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

    /**
     * Get the file path for a cache key
     *
     * @param  string  $key  The cache key
     * @return string The file path
     */
    private function getFilePath(string $key): string
    {
        $hash = md5($key);

        // Use the first two characters as a directory to avoid too many files in one directory
        $path = $this->directory.'/'.substr($hash, 0, 2);

        return $path.'/'.$hash;
    }

    /**
     * Read a cache file and return its contents
     *
     * @param  string  $path  The path to the cache file
     * @return array<string, mixed>|null The cache data or null if the file could not be read
     */
    private function readCacheFile(string $path): ?array
    {
        $content = @file_get_contents($path);

        if ($content === false) {
            return null;
        }

        try {
            $data = unserialize($content);

            if (! is_array($data)) {
                return null;
            }

            // Ensure the array has the expected structure
            if (! isset($data['value']) || ! array_key_exists('expires', $data)) {
                return null;
            }

            /** @var array<string, mixed> $data */
            return $data;
        } catch (\Exception $e) {
            return null;
        }
    }
}

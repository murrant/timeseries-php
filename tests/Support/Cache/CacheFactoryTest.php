<?php

namespace TimeSeriesPhp\Tests\Support\Cache;

use PHPUnit\Framework\TestCase;
use Psr\SimpleCache\CacheInterface;
use TimeSeriesPhp\Config\CacheConfig;
use TimeSeriesPhp\Exceptions\ConfigurationException;
use TimeSeriesPhp\Support\Cache\CacheFactory;
use TimeSeriesPhp\Support\Cache\FileCache;
use TimeSeriesPhp\Support\Cache\MemoryCache;

class CacheFactoryTest extends TestCase
{
    public function test_make_returns_memory_cache_by_default(): void
    {
        $cache = CacheFactory::make();

        $this->assertInstanceOf(CacheInterface::class, $cache);
        $this->assertInstanceOf(MemoryCache::class, $cache);
    }

    public function test_create_returns_memory_cache_with_memory_driver(): void
    {
        $config = new CacheConfig([
            'enabled' => true,
            'driver' => 'memory',
        ]);

        $cache = CacheFactory::create($config);

        $this->assertInstanceOf(CacheInterface::class, $cache);
        $this->assertInstanceOf(MemoryCache::class, $cache);
    }

    public function test_create_returns_file_cache_with_file_driver(): void
    {
        $config = new CacheConfig([
            'enabled' => true,
            'driver' => 'file',
            'file' => [
                'path' => '/tmp/tsdb_cache_test',
                'permissions' => 0755,
            ],
        ]);

        $cache = CacheFactory::create($config);

        $this->assertInstanceOf(CacheInterface::class, $cache);
        $this->assertInstanceOf(FileCache::class, $cache);
    }

    public function test_create_throws_exception_with_unsupported_driver(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('Invalid value for configuration field \'driver\'');

        $config = new CacheConfig([
            'enabled' => true,
            'driver' => 'unsupported',
        ]);
    }
}

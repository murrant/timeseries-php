<?php

declare(strict_types=1);

namespace TimeSeriesPhp\Tests\Services;

use PHPUnit\Framework\TestCase;
use TimeSeriesPhp\Services\Cache;

class CacheTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempDir = sys_get_temp_dir().'/tsdb_test_cache_'.uniqid();
        mkdir($this->tempDir, 0755, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tempDir);
        parent::tearDown();
    }

    private function removeDirectory(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($files as $file) {
            if ($file->isDir()) {
                rmdir($file->getRealPath());
            } else {
                unlink($file->getRealPath());
            }
        }

        rmdir($dir);
    }

    public function test_array_cache(): void
    {
        $config = [
            'enabled' => true,
            'ttl' => 3600,
            'driver' => 'array',
            'prefix' => 'test_',
        ];

        $cache = new Cache($config);

        // Test set and get
        $this->assertTrue($cache->set('key1', 'value1'));
        $this->assertEquals('value1', $cache->get('key1'));

        // Test has
        $this->assertTrue($cache->has('key1'));
        $this->assertFalse($cache->has('nonexistent'));

        // Test delete
        $this->assertTrue($cache->delete('key1'));
        $this->assertFalse($cache->has('key1'));

        // Test default value
        $this->assertEquals('default', $cache->get('nonexistent', 'default'));

        // Test clear
        $cache->set('key2', 'value2');
        $this->assertTrue($cache->clear());
        $this->assertFalse($cache->has('key2'));
    }

    public function test_file_cache(): void
    {
        $config = [
            'enabled' => true,
            'ttl' => 3600,
            'driver' => 'file',
            'prefix' => 'test_',
            'file' => [
                'directory' => $this->tempDir,
            ],
        ];

        $cache = new Cache($config);

        // Test set and get
        $this->assertTrue($cache->set('key1', 'value1'));
        $this->assertEquals('value1', $cache->get('key1'));

        // Test has
        $this->assertTrue($cache->has('key1'));
        $this->assertFalse($cache->has('nonexistent'));

        // Test delete
        $this->assertTrue($cache->delete('key1'));
        $this->assertFalse($cache->has('key1'));

        // Test default value
        $this->assertEquals('default', $cache->get('nonexistent', 'default'));

        // Test clear
        $cache->set('key2', 'value2');
        $this->assertTrue($cache->clear());
        $this->assertFalse($cache->has('key2'));
    }

    public function test_multiple_operations(): void
    {
        $config = [
            'enabled' => true,
            'ttl' => 3600,
            'driver' => 'array',
            'prefix' => 'test_',
        ];

        $cache = new Cache($config);

        // Test setMultiple and getMultiple
        $values = [
            'key1' => 'value1',
            'key2' => 'value2',
            'key3' => 'value3',
        ];

        $this->assertTrue($cache->setMultiple($values));
        $result = $cache->getMultiple(array_keys($values));

        foreach ($values as $key => $value) {
            $this->assertEquals($value, $result[$key]);
        }

        // Test deleteMultiple
        $this->assertTrue($cache->deleteMultiple(['key1', 'key2']));
        $this->assertFalse($cache->has('key1'));
        $this->assertFalse($cache->has('key2'));
        $this->assertTrue($cache->has('key3'));
    }

    public function test_expiration(): void
    {
        $config = [
            'enabled' => true,
            'ttl' => 1, // 1 second TTL
            'driver' => 'array',
            'prefix' => 'test_',
        ];

        $cache = new Cache($config);

        // Set a value with a 1 second TTL
        $this->assertTrue($cache->set('key1', 'value1'));
        $this->assertEquals('value1', $cache->get('key1'));

        // Wait for the item to expire
        sleep(2);

        // The item should be expired now
        $this->assertFalse($cache->has('key1'));
        $this->assertEquals('default', $cache->get('key1', 'default'));
    }

    public function test_disabled_cache(): void
    {
        $config = [
            'enabled' => false,
            'driver' => 'array',
        ];

        $cache = new Cache($config);

        // Set should succeed but not actually store anything
        $this->assertTrue($cache->set('key1', 'value1'));

        // Get should return the default value
        $this->assertEquals('default', $cache->get('key1', 'default'));

        // Has should return false
        $this->assertFalse($cache->has('key1'));
    }
}

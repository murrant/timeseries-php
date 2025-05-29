<?php

namespace TimeSeriesPhp\Tests\Support\Cache;

use PHPUnit\Framework\TestCase;
use TimeSeriesPhp\Support\Cache\MemoryCache;
use TimeSeriesPhp\Support\Config\CacheConfig;

class MemoryCacheTest extends TestCase
{
    private MemoryCache $cache;

    protected function setUp(): void
    {
        parent::setUp();

        $config = new CacheConfig([
            'enabled' => true,
            'driver' => 'memory',
            'prefix' => 'test_',
        ]);

        $this->cache = new MemoryCache($config);
    }

    public function test_get_returns_default_when_key_does_not_exist(): void
    {
        $result = $this->cache->get('non_existent_key', 'default_value');

        $this->assertEquals('default_value', $result);
    }

    public function test_set_and_get_value(): void
    {
        $key = 'test_key';
        $value = 'test_value';

        $result = $this->cache->set($key, $value);

        $this->assertTrue($result);
        $this->assertEquals($value, $this->cache->get($key));
    }

    public function test_set_and_get_complex_value(): void
    {
        $key = 'complex_key';
        $value = ['foo' => 'bar', 'baz' => [1, 2, 3]];

        $result = $this->cache->set($key, $value);

        $this->assertTrue($result);
        $this->assertEquals($value, $this->cache->get($key));
    }

    public function test_has_returns_true_when_key_exists(): void
    {
        $key = 'existing_key';
        $this->cache->set($key, 'some_value');

        $result = $this->cache->has($key);

        $this->assertTrue($result);
    }

    public function test_has_returns_false_when_key_does_not_exist(): void
    {
        $result = $this->cache->has('non_existent_key');

        $this->assertFalse($result);
    }

    public function test_delete_removes_key(): void
    {
        $key = 'key_to_delete';
        $this->cache->set($key, 'value_to_delete');

        $result = $this->cache->delete($key);

        $this->assertTrue($result);
        $this->assertFalse($this->cache->has($key));
        $this->assertEquals('default', $this->cache->get($key, 'default'));
    }

    public function test_clear_removes_all_keys(): void
    {
        $this->cache->set('key1', 'value1');
        $this->cache->set('key2', 'value2');

        $result = $this->cache->clear();

        $this->assertTrue($result);
        $this->assertFalse($this->cache->has('key1'));
        $this->assertFalse($this->cache->has('key2'));
    }

    public function test_get_multiple_returns_values_for_existing_keys(): void
    {
        $this->cache->set('key1', 'value1');
        $this->cache->set('key2', 'value2');

        $result = $this->cache->getMultiple(['key1', 'key2', 'key3'], 'default');

        $this->assertEquals([
            'key1' => 'value1',
            'key2' => 'value2',
            'key3' => 'default',
        ], $result);
    }

    public function test_set_multiple_sets_multiple_values(): void
    {
        $values = [
            'multi_key1' => 'multi_value1',
            'multi_key2' => 'multi_value2',
        ];

        $result = $this->cache->setMultiple($values);

        $this->assertTrue($result);
        $this->assertEquals('multi_value1', $this->cache->get('multi_key1'));
        $this->assertEquals('multi_value2', $this->cache->get('multi_key2'));
    }

    public function test_delete_multiple_removes_multiple_keys(): void
    {
        $this->cache->set('del_key1', 'del_value1');
        $this->cache->set('del_key2', 'del_value2');
        $this->cache->set('keep_key', 'keep_value');

        $result = $this->cache->deleteMultiple(['del_key1', 'del_key2']);

        $this->assertTrue($result);
        $this->assertFalse($this->cache->has('del_key1'));
        $this->assertFalse($this->cache->has('del_key2'));
        $this->assertTrue($this->cache->has('keep_key'));
    }

    public function test_cache_is_disabled_when_config_says_disabled(): void
    {
        $config = new CacheConfig([
            'enabled' => false,
            'driver' => 'memory',
        ]);

        $disabledCache = new MemoryCache($config);

        // Set should return false when cache is disabled
        $this->assertFalse($disabledCache->set('key', 'value'));

        // Get should return default value when cache is disabled
        $this->assertEquals('default', $disabledCache->get('key', 'default'));

        // Has should return false when cache is disabled
        $this->assertFalse($disabledCache->has('key'));

        // Delete should return false when cache is disabled
        $this->assertFalse($disabledCache->delete('key'));

        // Clear should return false when cache is disabled
        $this->assertFalse($disabledCache->clear());
    }
}

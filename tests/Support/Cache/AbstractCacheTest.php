<?php

namespace TimeSeriesPhp\Tests\Support\Cache;

use PHPUnit\Framework\TestCase;
use TimeSeriesPhp\Exceptions\TSDBException;
use TimeSeriesPhp\Support\Cache\AbstractCache;
use TimeSeriesPhp\Support\Config\CacheConfig;

class AbstractCacheTest extends TestCase
{
    private ConcreteCacheForTesting $cache;

    protected function setUp(): void
    {
        parent::setUp();

        $config = new CacheConfig([
            'enabled' => true,
            'driver' => 'memory',
            'prefix' => 'test_',
        ]);

        $this->cache = new ConcreteCacheForTesting($config);
    }

    public function test_get_multiple_calls_get_for_each_key(): void
    {
        // Set up the mock expectations
        $this->cache->setMockValues([
            'key1' => 'value1',
            'key2' => 'value2',
        ]);

        $result = $this->cache->getMultiple(['key1', 'key2', 'key3'], 'default');

        $this->assertEquals([
            'key1' => 'value1',
            'key2' => 'value2',
            'key3' => 'default',
        ], $result);

        // Verify get was called for each key
        $this->assertEquals(['key1', 'key2', 'key3'], $this->cache->getCalledWith);
    }

    public function test_get_multiple_throws_exception_for_non_string_key(): void
    {
        $this->expectException(TSDBException::class);
        $this->expectExceptionMessage('Cache key must be a string');

        // @phpstan-ignore-next-line
        $this->cache->getMultiple([123]);
    }

    public function test_set_multiple_calls_set_for_each_key_value(): void
    {
        $values = [
            'key1' => 'value1',
            'key2' => 'value2',
        ];

        $result = $this->cache->setMultiple($values);

        $this->assertTrue($result);

        // Verify set was called for each key-value pair
        $this->assertEquals([
            ['key1', 'value1', null],
            ['key2', 'value2', null],
        ], $this->cache->setCalledWith);
    }

    public function test_set_multiple_throws_exception_for_non_string_key(): void
    {
        $this->expectException(TSDBException::class);
        $this->expectExceptionMessage('Cache key must be a string');

        // @phpstan-ignore-next-line
        $this->cache->setMultiple([123 => 'value']);
    }

    public function test_set_multiple_returns_false_if_any_set_fails(): void
    {
        // Make the second set operation fail
        $this->cache->setFailOn = 'key2';

        $values = [
            'key1' => 'value1',
            'key2' => 'value2',
        ];

        $result = $this->cache->setMultiple($values);

        $this->assertFalse($result);
    }

    public function test_delete_multiple_calls_delete_for_each_key(): void
    {
        $result = $this->cache->deleteMultiple(['key1', 'key2']);

        $this->assertTrue($result);

        // Verify delete was called for each key
        $this->assertEquals(['key1', 'key2'], $this->cache->deleteCalledWith);
    }

    public function test_delete_multiple_throws_exception_for_non_string_key(): void
    {
        $this->expectException(TSDBException::class);
        $this->expectExceptionMessage('Cache key must be a string');

        // @phpstan-ignore-next-line
        $this->cache->deleteMultiple([123]);
    }

    public function test_delete_multiple_returns_false_if_any_delete_fails(): void
    {
        // Make the second delete operation fail
        $this->cache->deleteFailOn = 'key2';

        $result = $this->cache->deleteMultiple(['key1', 'key2']);

        $this->assertFalse($result);
    }
}

/**
 * Concrete implementation of AbstractCache for testing
 */
class ConcreteCacheForTesting extends AbstractCache
{
    /** @var array<string, mixed> */
    private array $mockValues = [];

    /** @var array<string> */
    public array $getCalledWith = [];

    /** @var array<array<mixed>> */
    public array $setCalledWith = [];

    /** @var array<string> */
    public array $deleteCalledWith = [];

    public ?string $setFailOn = null;

    public ?string $deleteFailOn = null;

    /**
     * @param  array<string, mixed>  $values
     */
    public function setMockValues(array $values): void
    {
        $this->mockValues = $values;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $this->getCalledWith[] = $key;

        return $this->mockValues[$key] ?? $default;
    }

    public function set(string $key, mixed $value, $ttl = null): bool
    {
        $this->setCalledWith[] = [$key, $value, $ttl];

        return $key !== $this->setFailOn;
    }

    public function delete(string $key): bool
    {
        $this->deleteCalledWith[] = $key;

        return $key !== $this->deleteFailOn;
    }

    public function clear(): bool
    {
        $this->mockValues = [];

        return true;
    }

    public function has(string $key): bool
    {
        return isset($this->mockValues[$key]);
    }
}

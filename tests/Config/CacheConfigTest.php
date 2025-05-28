<?php

namespace TimeSeriesPhp\Tests\Config;

use TimeSeriesPhp\Config\CacheConfig;

class CacheConfigTest extends ConfigTestCase
{
    protected function createConfig(array $config): CacheConfig
    {
        return new CacheConfig($config);
    }

    public function test_default_values(): void
    {
        $config = $this->createConfig([]);

        $this->assertFalse($config->isEnabled());
        $this->assertEquals('memory', $config->getString('driver'));
        $this->assertEquals(300, $config->getInt('ttl'));
        $this->assertEquals('php', $config->getString('serialization'));
    }

    public function test_is_enabled(): void
    {
        $config = $this->createConfig(['enabled' => true]);
        $this->assertTrue($config->isEnabled());

        $config = $this->createConfig(['enabled' => false]);
        $this->assertFalse($config->isEnabled());
    }

    public function test_get_driver_config(): void
    {
        $redisConfig = [
            'host' => 'redis-server',
            'port' => 6380,
            'database' => 1,
        ];

        $config = $this->createConfig([
            'driver' => 'redis',
            'redis' => $redisConfig,
        ]);

        $this->assertEquals($redisConfig, $config->getDriverConfig());
        $this->assertEquals($redisConfig, $config->getDriverConfig('redis'));

        // Test getting config for a different driver than the configured one
        $memcachedConfig = [
            'servers' => [['memcached-server', 11211]],
        ];

        $config = $this->createConfig([
            'driver' => 'redis',
            'redis' => $redisConfig,
            'memcached' => $memcachedConfig,
        ]);

        $this->assertEquals($memcachedConfig, $config->getDriverConfig('memcached'));
    }

    public function test_validation(): void
    {
        $this->expectNotToPerformAssertions();
        // Valid configuration should not throw an exception
        $config = $this->createConfig([
            'ttl' => 600,
            'driver' => 'redis',
        ]);
    }
}

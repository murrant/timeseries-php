<?php

namespace TimeSeriesPhp\Tests\Config;

use TimeSeriesPhp\Config\CacheConfig;
use TimeSeriesPhp\Config\ConfigInterface;

class CacheConfigTest extends ConfigTestCase
{
    protected function createConfig(array $config): ConfigInterface
    {
        return new CacheConfig($config);
    }
    
    public function testDefaultValues()
    {
        $config = $this->createConfig([]);
        
        $this->assertFalse($config->isEnabled());
        $this->assertEquals('memory', $config->get('driver'));
        $this->assertEquals(300, $config->get('ttl'));
        $this->assertEquals('php', $config->get('serialization'));
    }
    
    public function testIsEnabled()
    {
        $config = $this->createConfig(['enabled' => true]);
        $this->assertTrue($config->isEnabled());
        
        $config = $this->createConfig(['enabled' => false]);
        $this->assertFalse($config->isEnabled());
    }
    
    public function testGetDriverConfig()
    {
        $redisConfig = [
            'host' => 'redis-server',
            'port' => 6380,
            'database' => 1
        ];
        
        $config = $this->createConfig([
            'driver' => 'redis',
            'redis' => $redisConfig
        ]);
        
        $this->assertEquals($redisConfig, $config->getDriverConfig());
        $this->assertEquals($redisConfig, $config->getDriverConfig('redis'));
        
        // Test getting config for a different driver than the configured one
        $memcachedConfig = [
            'servers' => [['memcached-server', 11211]]
        ];
        
        $config = $this->createConfig([
            'driver' => 'redis',
            'redis' => $redisConfig,
            'memcached' => $memcachedConfig
        ]);
        
        $this->assertEquals($memcachedConfig, $config->getDriverConfig('memcached'));
    }
    
    public function testValidation()
    {
        // Valid configuration should not throw an exception
        $config = $this->createConfig([
            'ttl' => 600,
            'driver' => 'redis'
        ]);
        
        $this->assertTrue(true); // If we got here, no exception was thrown
    }
}

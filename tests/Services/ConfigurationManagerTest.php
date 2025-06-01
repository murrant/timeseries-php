<?php

declare(strict_types=1);

namespace TimeSeriesPhp\Tests\Services;

use PHPUnit\Framework\TestCase;
use TimeSeriesPhp\Core\Configuration;
use TimeSeriesPhp\Exceptions\TSDBException;
use TimeSeriesPhp\Services\ConfigurationManager;

class ConfigurationManagerTest extends TestCase
{
    private string $configDir;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a temporary config directory for testing
        $this->configDir = sys_get_temp_dir().'/timeseries-php-test-'.uniqid();
        mkdir($this->configDir);
        mkdir($this->configDir.'/packages');

        // Create a test configuration file
        $configContent = <<<'YAML'
default_driver: 'test_driver'
drivers:
    test_driver:
        url: 'http://localhost:9999'
        token: 'test_token'
        org: 'test_org'
        bucket: 'test_bucket'
        precision: 'ms'
    another_driver:
        url: 'http://localhost:8888'
cache:
    enabled: true
    ttl: 1800
YAML;

        file_put_contents($this->configDir.'/packages/config.yaml', $configContent);
    }

    protected function tearDown(): void
    {
        // Clean up the temporary config directory
        if (file_exists($this->configDir.'/packages/config.yaml')) {
            unlink($this->configDir.'/packages/config.yaml');
        }
        if (is_dir($this->configDir.'/packages')) {
            rmdir($this->configDir.'/packages');
        }
        if (is_dir($this->configDir)) {
            rmdir($this->configDir);
        }

        parent::tearDown();
    }

    public function test_get_config(): void
    {
        $configManager = new ConfigurationManager($this->configDir);
        $config = $configManager->getConfig();

        $this->assertIsArray($config);
        $this->assertArrayHasKey('default_driver', $config);
        $this->assertEquals('test_driver', $config['default_driver']);

        $this->assertArrayHasKey('drivers', $config);
        $this->assertIsArray($config['drivers']);

        $this->assertArrayHasKey('test_driver', $config['drivers']);
        $this->assertIsArray($config['drivers']['test_driver']);

        $this->assertArrayHasKey('url', $config['drivers']['test_driver']);
        $this->assertEquals('http://localhost:9999', $config['drivers']['test_driver']['url']);
    }

    public function test_get(): void
    {
        $configManager = new ConfigurationManager($this->configDir);

        $this->assertEquals('test_driver', $configManager->get('default_driver'));
        $this->assertEquals('http://localhost:9999', $configManager->get('drivers.test_driver.url'));
        $this->assertEquals('test_token', $configManager->get('drivers.test_driver.token'));
        $this->assertEquals(1800, $configManager->get('cache.ttl'));
        $this->assertTrue($configManager->get('cache.enabled'));

        // Test with default value
        $this->assertNull($configManager->get('non_existent_key'));
        $this->assertEquals('default_value', $configManager->get('non_existent_key', 'default_value'));
    }

    public function test_get_default_driver_config(): void
    {
        $configManager = new ConfigurationManager($this->configDir);
        $driverConfig = $configManager->getDefaultDriverConfig();

        $this->assertIsArray($driverConfig);
        $this->assertArrayHasKey('url', $driverConfig);
        $this->assertEquals('http://localhost:9999', $driverConfig['url']);
        $this->assertEquals('test_token', $driverConfig['token']);
        $this->assertEquals('test_org', $driverConfig['org']);
        $this->assertEquals('test_bucket', $driverConfig['bucket']);
        $this->assertEquals('ms', $driverConfig['precision']);
    }

    public function test_get_driver_config(): void
    {
        $configManager = new ConfigurationManager($this->configDir);

        // Test getting a specific driver config
        $driverConfig = $configManager->getDriverConfig('test_driver');
        $this->assertIsArray($driverConfig);
        $this->assertEquals('http://localhost:9999', $driverConfig['url']);

        $anotherDriverConfig = $configManager->getDriverConfig('another_driver');
        $this->assertIsArray($anotherDriverConfig);
        $this->assertEquals('http://localhost:8888', $anotherDriverConfig['url']);

        // Test getting a non-existent driver config
        $this->expectException(TSDBException::class);
        $configManager->getDriverConfig('non_existent_driver');
    }
}

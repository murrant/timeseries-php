<?php

declare(strict_types=1);

namespace TimeSeriesPhp\Tests\Drivers\Null;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;
use TimeSeriesPhp\Drivers\Null\NullConfig;

class NullDriverConfigurationTest extends TestCase
{
    private NullConfig $configuration;

    private Processor $processor;

    protected function setUp(): void
    {
        $this->configuration = new NullConfig;
        $this->processor = new Processor;
    }

    public function test_implements_configuration_interface(): void
    {
        // Assert that the configuration implements ConfigurationInterface
        $this->assertInstanceOf(ConfigurationInterface::class, $this->configuration);
    }

    public function test_get_config_name(): void
    {
        // Use reflection to access the protected method
        $reflectionMethod = new \ReflectionMethod(NullConfig::class, 'getConfigName');
        $reflectionMethod->setAccessible(true);

        // Get the config name
        $configName = $reflectionMethod->invoke($this->configuration);

        // Assert that the config name is correct
        $this->assertEquals('null', $configName);
    }

    public function test_process_configuration(): void
    {
        // Process a valid configuration
        $config = $this->configuration->processConfiguration([
            'database' => 'test_db',
            'host' => 'example.com',
            'port' => 8086,
            'username' => 'user',
            'password' => 'pass',
            'debug' => true,
        ]);

        // Assert that the processed configuration has the expected values
        $this->assertEquals('test_db', $config['database']);
        $this->assertEquals('example.com', $config['host']);
        $this->assertEquals(8086, $config['port']);
        $this->assertEquals('user', $config['username']);
        $this->assertEquals('pass', $config['password']);
        $this->assertTrue($config['debug']);
    }

    public function test_process_configuration_with_defaults(): void
    {
        // Process a minimal configuration
        $config = $this->configuration->processConfiguration([
            'database' => 'test_db',
        ]);

        // Assert that the processed configuration has the expected default values
        $this->assertEquals('test_db', $config['database']);
        $this->assertEquals('localhost', $config['host']);
        $this->assertNull($config['port']);
        $this->assertNull($config['username']);
        $this->assertNull($config['password']);
        $this->assertFalse($config['debug']);
    }

    public function test_process_configuration_without_database(): void
    {
        // Expect an exception when processing a configuration without a database
        $this->expectException(InvalidConfigurationException::class);

        $this->configuration->processConfiguration([
            'host' => 'example.com',
        ]);
    }

    public function test_debug_configuration(): void
    {
        // Test with debug explicitly set to true
        $config = $this->configuration->processConfiguration([
            'database' => 'test_db',
            'debug' => true,
        ]);
        $this->assertTrue($config['debug']);

        // Test with debug explicitly set to false
        $config = $this->configuration->processConfiguration([
            'database' => 'test_db',
            'debug' => false,
        ]);
        $this->assertFalse($config['debug']);
    }
}

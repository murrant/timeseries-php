<?php

declare(strict_types=1);

namespace TimeSeriesPhp\Tests\Drivers\Example;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;
use TimeSeriesPhp\Drivers\Example\ExampleDriverConfiguration;

class ExampleDriverConfigurationTest extends TestCase
{
    private ExampleDriverConfiguration $configuration;
    private Processor $processor;

    protected function setUp(): void
    {
        $this->configuration = new ExampleDriverConfiguration();
        $this->processor = new Processor();
    }
    
    public function testImplementsConfigurationInterface(): void
    {
        // Assert that the configuration implements ConfigurationInterface
        $this->assertInstanceOf(ConfigurationInterface::class, $this->configuration);
    }
    
    public function testGetConfigName(): void
    {
        // Use reflection to access the protected method
        $reflectionMethod = new \ReflectionMethod(ExampleDriverConfiguration::class, 'getConfigName');
        $reflectionMethod->setAccessible(true);
        
        // Get the config name
        $configName = $reflectionMethod->invoke($this->configuration);
        
        // Assert that the config name is correct
        $this->assertEquals('example', $configName);
    }
    
    public function testProcessConfiguration(): void
    {
        // Process a valid configuration
        $config = $this->configuration->processConfiguration([
            'database' => 'test_db',
            'host' => 'example.com',
            'port' => 8086,
            'username' => 'user',
            'password' => 'pass',
            'use_ssl' => true,
            'timeout' => 60,
            'mode' => 'advanced',
        ]);
        
        // Assert that the processed configuration has the expected values
        $this->assertEquals('test_db', $config['database']);
        $this->assertEquals('example.com', $config['host']);
        $this->assertEquals(8086, $config['port']);
        $this->assertEquals('user', $config['username']);
        $this->assertEquals('pass', $config['password']);
        $this->assertTrue($config['use_ssl']);
        $this->assertEquals(60, $config['timeout']);
        $this->assertEquals('advanced', $config['mode']);
    }
    
    public function testProcessConfigurationWithDefaults(): void
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
        $this->assertFalse($config['use_ssl']);
        $this->assertEquals(30, $config['timeout']);
        $this->assertEquals('standard', $config['mode']);
    }
    
    public function testProcessConfigurationWithInvalidMode(): void
    {
        // Expect an exception when processing a configuration with an invalid mode
        $this->expectException(InvalidConfigurationException::class);
        
        $this->configuration->processConfiguration([
            'database' => 'test_db',
            'mode' => 'invalid',
        ]);
    }
    
    public function testProcessConfigurationWithoutDatabase(): void
    {
        // Expect an exception when processing a configuration without a database
        $this->expectException(InvalidConfigurationException::class);
        
        $this->configuration->processConfiguration([
            'host' => 'example.com',
        ]);
    }
}

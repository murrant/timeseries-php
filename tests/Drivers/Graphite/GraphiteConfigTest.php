<?php

declare(strict_types=1);

namespace TimeSeriesPhp\Tests\Drivers\Graphite;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use TimeSeriesPhp\Drivers\Graphite\Config\GraphiteConfig;

class GraphiteConfigTest extends TestCase
{
    private GraphiteConfig $configuration;

    protected function setUp(): void
    {
        $this->configuration = new GraphiteConfig;
    }

    public function test_implements_configuration_interface(): void
    {
        // Assert that the configuration implements ConfigurationInterface
        $this->assertInstanceOf(ConfigurationInterface::class, $this->configuration);
    }

    public function test_get_config_name(): void
    {
        // Use reflection to access the protected method
        $reflectionMethod = new \ReflectionMethod(GraphiteConfig::class, 'getConfigName');
        $reflectionMethod->setAccessible(true);

        // Get the config name
        $configName = $reflectionMethod->invoke($this->configuration);

        // Assert that the config name is correct
        $this->assertEquals('graphite', $configName);
    }

    public function test_default_values(): void
    {
        // Test the default values directly on the instance
        $this->assertEquals('localhost', $this->configuration->host);
        $this->assertEquals(2003, $this->configuration->port);
        $this->assertEquals('tcp', $this->configuration->protocol);
        $this->assertEquals(30, $this->configuration->timeout);
        $this->assertEquals('', $this->configuration->prefix);
        $this->assertEquals(500, $this->configuration->batch_size);
        $this->assertEquals('localhost', $this->configuration->web_host);
        $this->assertEquals(8080, $this->configuration->web_port);
        $this->assertEquals('http', $this->configuration->web_protocol);
        $this->assertEquals('/render', $this->configuration->web_path);
    }

    public function test_process_configuration(): void
    {
        // Process a valid configuration
        $config = $this->configuration->processConfiguration([
            'database' => 'test_db',
            'host' => 'graphite.example.com',
            'port' => 2004,
            'protocol' => 'udp',
            'timeout' => 60,
            'prefix' => 'myapp',
            'batch_size' => 1000,
            'web_host' => 'graphite-web.example.com',
            'web_port' => 8081,
            'web_protocol' => 'https',
            'web_path' => '/api/render',
        ]);

        // Assert that the processed configuration has the expected values
        $this->assertEquals('test_db', $config['database']);
        $this->assertEquals('graphite.example.com', $config['host']);
        $this->assertEquals(2004, $config['port']);
        $this->assertEquals('udp', $config['protocol']);
        $this->assertEquals(60, $config['timeout']);
        $this->assertEquals('myapp', $config['prefix']);
        $this->assertEquals(1000, $config['batch_size']);
        $this->assertEquals('graphite-web.example.com', $config['web_host']);
        $this->assertEquals(8081, $config['web_port']);
        $this->assertEquals('https', $config['web_protocol']);
        $this->assertEquals('/api/render', $config['web_path']);
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
        $this->assertEquals(2003, $config['port']);
        $this->assertEquals('tcp', $config['protocol']);
        $this->assertEquals(30, $config['timeout']);
        $this->assertEquals('', $config['prefix']);
        $this->assertEquals(500, $config['batch_size']);
        $this->assertEquals('localhost', $config['web_host']);
        $this->assertEquals(8080, $config['web_port']);
        $this->assertEquals('http', $config['web_protocol']);
        $this->assertEquals('/render', $config['web_path']);
    }

    public function test_invalid_protocol(): void
    {
        // Expect an exception when processing a configuration with an invalid protocol
        $this->expectException(InvalidConfigurationException::class);

        $this->configuration->processConfiguration([
            'database' => 'test_db',
            'protocol' => 'invalid',
        ]);
    }

    public function test_invalid_web_protocol(): void
    {
        // Expect an exception when processing a configuration with an invalid web protocol
        $this->expectException(InvalidConfigurationException::class);

        $this->configuration->processConfiguration([
            'database' => 'test_db',
            'web_protocol' => 'invalid',
        ]);
    }

    public function test_get_connection_string(): void
    {
        // Create a configuration with custom host and port
        $config = new GraphiteConfig(
            host: 'graphite.example.com',
            port: 2004
        );

        // Test the getConnectionString method
        $this->assertEquals('graphite.example.com:2004', $config->getConnectionString());
    }

    public function test_get_web_url(): void
    {
        // Create a configuration with custom web settings
        $config = new GraphiteConfig(
            web_host: 'graphite-web.example.com',
            web_port: 8081,
            web_protocol: 'https',
            web_path: '/api/render'
        );

        // Test the getWebUrl method
        $this->assertEquals('https://graphite-web.example.com:8081/api/render', $config->getWebUrl());
    }
}

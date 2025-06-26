<?php

declare(strict_types=1);

namespace TimeSeriesPhp\Tests\Drivers\Null;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use TimeSeriesPhp\Drivers\Null\NullConfig;

class NullDriverConfigurationTest extends TestCase
{
    private NullConfig $configuration;

    protected function setUp(): void
    {
        $this->configuration = new NullConfig;
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
            'debug' => true,
        ]);

        // Assert that the processed configuration has the expected values
        $this->assertTrue($config['debug']);
    }

    public function test_process_configuration_with_defaults(): void
    {
        // Process a minimal configuration
        $config = $this->configuration->processConfiguration([]);

        // Assert that the processed configuration has the expected default values
        $this->assertFalse($config['debug']);
    }

    public function test_debug_configuration(): void
    {
        // Test with debug explicitly set to true
        $config = $this->configuration->processConfiguration([
            'debug' => true,
        ]);
        $this->assertTrue($config['debug']);

        // Test with debug explicitly set to false
        $config = $this->configuration->processConfiguration([
            'debug' => false,
        ]);
        $this->assertFalse($config['debug']);
    }
}

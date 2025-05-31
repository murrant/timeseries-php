<?php

declare(strict_types=1);

namespace TimeSeriesPhp\Tests\Core\Driver;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use TimeSeriesPhp\Core\Driver\AbstractDriverConfiguration;

class AbstractDriverConfigurationTest extends TestCase
{
    /**
     * Test implementation of AbstractDriverConfiguration
     */
    private $configuration;

    protected function setUp(): void
    {
        // Create a test implementation of AbstractDriverConfiguration
        $this->configuration = new class extends AbstractDriverConfiguration
        {
            protected function getConfigName(): string
            {
                return 'test';
            }

            protected function configureSchema(ArrayNodeDefinition $rootNode): void
            {
                $rootNode
                    ->children()
                    ->booleanNode('test_option')
                    ->defaultTrue()
                    ->end()
                    ->end();
            }
        };
    }

    public function test_implements_configuration_interface(): void
    {
        // Assert that the configuration implements ConfigurationInterface
        $this->assertInstanceOf(ConfigurationInterface::class, $this->configuration);
    }

    public function test_get_config_tree_builder(): void
    {
        // Get the tree builder
        $treeBuilder = $this->configuration->getConfigTreeBuilder();

        // Assert that the tree builder is an instance of TreeBuilder
        $this->assertInstanceOf(TreeBuilder::class, $treeBuilder);
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
            'test_option' => false,
        ]);

        // Assert that the processed configuration has the expected values
        $this->assertEquals('test_db', $config['database']);
        $this->assertEquals('example.com', $config['host']);
        $this->assertEquals(8086, $config['port']);
        $this->assertEquals('user', $config['username']);
        $this->assertEquals('pass', $config['password']);
        $this->assertFalse($config['test_option']);
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
        $this->assertTrue($config['test_option']);
        $this->assertIsArray($config['options']);
        $this->assertEmpty($config['options']);
    }
}

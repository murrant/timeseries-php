<?php

declare(strict_types=1);

namespace TimeSeriesPhp\Core\Driver;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * Abstract base class for driver configuration
 */
abstract class AbstractDriverConfiguration implements ConfigurationInterface
{
    /**
     * Get the configuration tree builder
     *
     * @return TreeBuilder The tree builder
     */
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder($this->getConfigName());
        $rootNode = $treeBuilder->getRootNode();
        
        // Define the base configuration schema
        $rootNode
            ->children()
                ->scalarNode('host')
                    ->info('The host to connect to')
                    ->defaultValue('localhost')
                ->end()
                ->integerNode('port')
                    ->info('The port to connect to')
                    ->defaultNull()
                ->end()
                ->scalarNode('username')
                    ->info('The username to authenticate with')
                    ->defaultNull()
                ->end()
                ->scalarNode('password')
                    ->info('The password to authenticate with')
                    ->defaultNull()
                ->end()
                ->scalarNode('database')
                    ->info('The default database to use')
                    ->isRequired()
                ->end()
                ->arrayNode('options')
                    ->info('Additional options for the driver')
                    ->useAttributeAsKey('name')
                    ->variablePrototype()->end()
                ->end()
            ->end();
        
        // Allow drivers to extend the configuration schema
        $this->configureSchema($rootNode);
        
        return $treeBuilder;
    }
    
    /**
     * Get the configuration name
     *
     * @return string The configuration name
     */
    abstract protected function getConfigName(): string;
    
    /**
     * Configure the schema for this driver
     *
     * @param \Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition $rootNode The root node
     * @return void
     */
    abstract protected function configureSchema(\Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition $rootNode): void;
    
    /**
     * Process the configuration
     *
     * @param array<string, mixed> $config The configuration to process
     * @return array<string, mixed> The processed configuration
     */
    public function processConfiguration(array $config): array
    {
        $processor = new \Symfony\Component\Config\Definition\Processor();
        return $processor->processConfiguration($this, [$config]);
    }
}

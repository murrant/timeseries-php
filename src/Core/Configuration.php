<?php

declare(strict_types=1);

namespace TimeSeriesPhp\Core;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * Configuration definition for the TimeSeriesPhp library
 */
class Configuration implements ConfigurationInterface
{
    /**
     * {@inheritdoc}
     */
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('timeseries');
        $rootNode = $treeBuilder->getRootNode();
        
        $rootNode
            ->children()
                ->scalarNode('default_driver')
                    ->defaultValue('influxdb')
                    ->info('Default driver to use when none is specified')
                ->end()
                ->arrayNode('drivers')
                    ->useAttributeAsKey('name')
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('url')
                                ->isRequired()
                                ->info('URL of the database server')
                            ->end()
                            ->scalarNode('token')
                                ->defaultValue('')
                                ->info('Authentication token')
                            ->end()
                            ->scalarNode('org')
                                ->defaultValue('')
                                ->info('Organization name')
                            ->end()
                            ->scalarNode('bucket')
                                ->defaultValue('default')
                                ->info('Bucket or database name')
                            ->end()
                            ->scalarNode('precision')
                                ->defaultValue('ns')
                                ->info('Time precision')
                            ->end()
                            ->arrayNode('options')
                                ->prototype('variable')->end()
                                ->info('Additional driver-specific options')
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('cache')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')
                            ->defaultTrue()
                            ->info('Whether to enable caching')
                        ->end()
                        ->integerNode('ttl')
                            ->defaultValue(3600)
                            ->info('Time to live in seconds')
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;
        
        return $treeBuilder;
    }
}

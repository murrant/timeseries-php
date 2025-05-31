<?php

declare(strict_types=1);

namespace TimeSeriesPhp\Drivers\Example;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use TimeSeriesPhp\Core\Driver\AbstractDriverConfiguration;

/**
 * Configuration for the Example driver
 */
class ExampleDriverConfiguration extends AbstractDriverConfiguration
{
    /**
     * Get the configuration name
     *
     * @return string The configuration name
     */
    protected function getConfigName(): string
    {
        return 'example';
    }

    /**
     * Configure the schema for this driver
     *
     * @param  ArrayNodeDefinition  $rootNode  The root node
     */
    protected function configureSchema(ArrayNodeDefinition $rootNode): void
    {
        $rootNode
            ->children()
                ->booleanNode('use_ssl')
                    ->info('Whether to use SSL for connections')
                    ->defaultFalse()
                ->end()
                ->integerNode('timeout')
                    ->info('Connection timeout in seconds')
                    ->defaultValue(30)
                ->end()
                ->enumNode('mode')
                    ->info('The operation mode')
                    ->values(['standard', 'advanced', 'compatibility'])
                    ->defaultValue('standard')
                ->end()
            ->end();
    }
}

<?php

declare(strict_types=1);

namespace TimeSeriesPhp\Drivers\Null;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use TimeSeriesPhp\Core\Driver\AbstractDriverConfiguration;

/**
 * Configuration for the Null driver
 */
class NullConfig extends AbstractDriverConfiguration
{
    /**
     * Get the configuration name
     *
     * @return string The configuration name
     */
    protected function getConfigName(): string
    {
        return 'null';
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
            ->booleanNode('debug')
            ->info('Whether to enable debug mode')
            ->defaultFalse()
            ->end()
            ->end();
    }
}

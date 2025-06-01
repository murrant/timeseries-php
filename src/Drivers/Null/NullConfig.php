<?php

declare(strict_types=1);

namespace TimeSeriesPhp\Drivers\Null;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use TimeSeriesPhp\Core\Attributes\Config;
use TimeSeriesPhp\Core\Driver\AbstractDriverConfiguration;

/**
 * Configuration for the Null driver
 */
#[Config('null', NullDriver::class)]
class NullConfig extends AbstractDriverConfiguration
{
    /**
     * @param  bool  $debug  Enable debug mode
     */
    public function __construct(
        public readonly bool $debug = false,
    ) {}

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
            ->info('Enable debug mode')
            ->defaultFalse()
            ->end()
            ->end();
    }
}

<?php

namespace TimeSeriesPhp\Drivers\Prometheus;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use TimeSeriesPhp\Core\Attributes\Config;
use TimeSeriesPhp\Core\Driver\AbstractDriverConfiguration;

/**
 * Configuration for the Prometheus driver
 */
#[Config('prometheus', PrometheusDriver::class)]
class PrometheusConfig extends AbstractDriverConfiguration
{
    /**
     * @param  string  $url  The Prometheus server URL
     * @param  int  $timeout  Connection timeout in seconds
     * @param  bool  $verify_ssl  Whether to verify SSL certificates
     * @param  bool  $debug  Enable debug mode
     */
    public function __construct(
        public readonly string $url = 'http://localhost:9090',
        public readonly int $timeout = 30,
        public readonly bool $verify_ssl = true,
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
            ->scalarNode('url')
            ->info('The Prometheus server URL')
            ->isRequired()
            ->cannotBeEmpty()
            ->end()
            ->integerNode('timeout')
            ->info('Connection timeout in seconds')
            ->defaultValue(30)
            ->min(1)
            ->end()
            ->booleanNode('verify_ssl')
            ->info('Whether to verify SSL certificates')
            ->defaultTrue()
            ->end()
            ->booleanNode('debug')
            ->info('Enable debug mode')
            ->defaultFalse()
            ->end()
            ->end();
    }
}

<?php

namespace TimeSeriesPhp\Drivers\Graphite;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use TimeSeriesPhp\Core\Attributes\Config;
use TimeSeriesPhp\Core\Driver\AbstractDriverConfiguration;

/**
 * Configuration for the Graphite driver
 */
#[Config('graphite', GraphiteDriver::class)]
class GraphiteConfig extends AbstractDriverConfiguration
{
    /**
     * @param  string  $host  The Graphite server host
     * @param  int  $port  The Graphite server port
     * @param  string  $protocol  The protocol to use (tcp or udp)
     * @param  int  $timeout  Connection timeout in seconds
     * @param  string  $prefix  Prefix for metrics
     * @param  int  $batch_size  Maximum number of metrics to send in a batch
     * @param  string  $web_host  The Graphite web server host
     * @param  int  $web_port  The Graphite web server port
     * @param  string  $web_protocol  The web protocol to use (http or https)
     * @param  string  $web_path  The path to the render API
     */
    public function __construct(
        public readonly string $host = 'localhost',
        public readonly int $port = 2003,
        public readonly string $protocol = 'tcp',
        public readonly int $timeout = 30,
        public readonly string $prefix = '',
        public readonly int $batch_size = 500,
        public readonly string $web_host = 'localhost',
        public readonly int $web_port = 8080,
        public readonly string $web_protocol = 'http',
        public readonly string $web_path = '/render',
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
            ->scalarNode('host')
            ->info('The Graphite server host')
            ->defaultValue('localhost')
            ->cannotBeEmpty()
            ->end()
            ->integerNode('port')
            ->info('The Graphite server port')
            ->defaultValue(2003)
            ->min(1)
            ->end()
            ->enumNode('protocol')
            ->info('The protocol to use (tcp or udp)')
            ->values(['tcp', 'udp'])
            ->defaultValue('tcp')
            ->end()
            ->integerNode('timeout')
            ->info('Connection timeout in seconds')
            ->defaultValue(30)
            ->min(1)
            ->end()
            ->scalarNode('prefix')
            ->info('Prefix for metrics')
            ->defaultValue('')
            ->end()
            ->integerNode('batch_size')
            ->info('Maximum number of metrics to send in a batch')
            ->defaultValue(500)
            ->min(1)
            ->end()
            ->scalarNode('web_host')
            ->info('The Graphite web server host')
            ->defaultValue('localhost')
            ->cannotBeEmpty()
            ->end()
            ->integerNode('web_port')
            ->info('The Graphite web server port')
            ->defaultValue(8080)
            ->min(1)
            ->end()
            ->enumNode('web_protocol')
            ->info('The web protocol to use (http or https)')
            ->values(['http', 'https'])
            ->defaultValue('http')
            ->end()
            ->scalarNode('web_path')
            ->info('The path to the render API')
            ->defaultValue('/render')
            ->cannotBeEmpty()
            ->end()
            ->end();
    }

    /**
     * Get the connection string for the Graphite server
     */
    public function getConnectionString(): string
    {
        return $this->host.':'.$this->port;
    }

    /**
     * Get the web URL for the Graphite server
     */
    public function getWebUrl(): string
    {
        return $this->web_protocol.'://'.$this->web_host.':'.$this->web_port.$this->web_path;
    }
}

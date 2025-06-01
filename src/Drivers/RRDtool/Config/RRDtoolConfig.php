<?php

namespace TimeSeriesPhp\Drivers\RRDtool\Config;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use TimeSeriesPhp\Core\Attributes\Config;
use TimeSeriesPhp\Core\Driver\AbstractDriverConfiguration;
use TimeSeriesPhp\Drivers\RRDtool\RRDtoolDriver;
use TimeSeriesPhp\Drivers\RRDtool\Tags\FileNameStrategy;

/**
 * Configuration for the RRDtool driver
 */
#[Config('rrdtool', RRDtoolDriver::class)]
class RRDtoolConfig extends AbstractDriverConfiguration
{
    /**
     * @param  string  $rrdtool_path  Path to the rrdtool executable
     * @param  string  $rrd_dir  Directory to store RRD files
     * @param  bool  $use_rrdcached  Whether to use rrdcached
     * @param  bool  $persistent_process  Whether to use a persistent rrdtool process
     * @param  int  $command_timeout  Command timeout in seconds
     * @param  string  $rrdcached_address  The rrdcached address
     * @param  int  $default_step  Default step in seconds
     * @param  bool  $debug  Enable debug mode
     * @param  string  $graph_output  Graph output format
     * @param  string  $tag_strategy  Tag strategy class
     * @param  array<string>  $default_archives  Default RRD archives
     */
    public function __construct(
        public readonly string $rrdtool_path = 'rrdtool',
        public readonly string $rrd_dir = '/tmp/rrd',
        public readonly bool $use_rrdcached = false,
        public readonly bool $persistent_process = true,
        public readonly int $command_timeout = 180,
        public readonly string $rrdcached_address = '',
        public readonly int $default_step = 300,
        public readonly bool $debug = false,
        public readonly string $graph_output = 'string',
        public readonly string $tag_strategy = FileNameStrategy::class,
        public readonly array $default_archives = [
            'RRA:AVERAGE:0.5:1:2016',      // 5min for 1 week
            'RRA:AVERAGE:0.5:12:1488',     // 1hour for 2 months
            'RRA:AVERAGE:0.5:288:366',     // 1day for 1 year
            'RRA:MAX:0.5:1:2016',          // 5min max for 1 week
            'RRA:MAX:0.5:12:1488',         // 1hour max for 2 months
            'RRA:MIN:0.5:1:2016',          // 5min min for 1 week
            'RRA:MIN:0.5:12:1488',         // 1hour min for 2 months
        ],
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
            ->scalarNode('rrdtool_path')
            ->info('Path to the rrdtool executable')
            ->defaultValue('rrdtool')
            ->cannotBeEmpty()
            ->end()
            ->scalarNode('rrd_dir')
            ->info('Directory to store RRD files')
            ->isRequired()
            ->cannotBeEmpty()
            ->end()
            ->booleanNode('use_rrdcached')
            ->info('Whether to use rrdcached')
            ->defaultFalse()
            ->end()
            ->booleanNode('persistent_process')
            ->info('Whether to use a persistent rrdtool process')
            ->defaultTrue()
            ->end()
            ->integerNode('command_timeout')
            ->info('Command timeout in seconds')
            ->defaultValue(180)
            ->min(0)
            ->end()
            ->scalarNode('rrdcached_address')
            ->info('The rrdcached address')
            ->defaultValue('')
            ->end()
            ->integerNode('default_step')
            ->info('Default step in seconds')
            ->defaultValue(300)
            ->min(1)
            ->end()
            ->booleanNode('debug')
            ->info('Enable debug mode')
            ->defaultFalse()
            ->end()
            ->enumNode('graph_output')
            ->info('Graph output format')
            ->values(['string', 'file'])
            ->defaultValue('string')
            ->end()
            ->scalarNode('tag_strategy')
            ->info('Tag strategy class')
            ->defaultValue(FileNameStrategy::class)
            ->cannotBeEmpty()
            ->end()
            ->arrayNode('default_archives')
            ->info('Default RRD archives')
            ->prototype('scalar')->end()
            ->defaultValue([
                'RRA:AVERAGE:0.5:1:2016',      // 5min for 1 week
                'RRA:AVERAGE:0.5:12:1488',     // 1hour for 2 months
                'RRA:AVERAGE:0.5:288:366',     // 1day for 1 year
                'RRA:MAX:0.5:1:2016',          // 5min max for 1 week
                'RRA:MAX:0.5:12:1488',         // 1hour max for 2 months
                'RRA:MIN:0.5:1:2016',          // 5min min for 1 week
                'RRA:MIN:0.5:12:1488',         // 1hour min for 2 months
            ])
            ->end()
            ->end();
    }
}

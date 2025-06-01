<?php

namespace TimeSeriesPhp\Drivers\RRDtool\Config;

use TimeSeriesPhp\Core\Attributes\Config;
use TimeSeriesPhp\Core\Config\AbstractConfig;
use TimeSeriesPhp\Drivers\RRDtool\RRDtoolDriver;
use TimeSeriesPhp\Drivers\RRDtool\Tags\FileNameStrategy;
use TimeSeriesPhp\Drivers\RRDtool\Tags\RRDTagStrategyInterface;
use TimeSeriesPhp\Exceptions\Config\ConfigurationException;

#[Config('rrdtool', RRDtoolDriver::class)]
class RRDtoolConfig extends AbstractConfig
{
    protected array $defaults = [
        'rrdtool_path' => 'rrdtool',
        'rrd_dir' => '/tmp/rrd',
        'use_rrdcached' => false,
        'persistent_process' => true,
        'command_timeout' => 180,
        'rrdcached_address' => '',
        'default_step' => 300,
        'debug' => false,
        'graph_output' => 'string', // or file
        'tag_strategy' => FileNameStrategy::class,
        'default_archives' => [
            'RRA:AVERAGE:0.5:1:2016',      // 5min for 1 week
            'RRA:AVERAGE:0.5:12:1488',     // 1hour for 2 months
            'RRA:AVERAGE:0.5:288:366',     // 1day for 1 year
            'RRA:MAX:0.5:1:2016',          // 5min max for 1 week
            'RRA:MAX:0.5:12:1488',         // 1hour max for 2 months
            'RRA:MIN:0.5:1:2016',          // 5min min for 1 week
            'RRA:MIN:0.5:12:1488',          // 1hour min for 2 months
        ],
    ];

    protected array $required = ['rrd_dir'];

    public function __construct(array $config = [])
    {
        $this->addValidator('rrdtool_path', fn ($path) => is_string($path) && ! empty($path));
        $this->addValidator('rrd_dir', fn ($dir) => is_string($dir) && ! empty($dir));
        $this->addValidator('debug', fn ($debug) => is_bool($debug));
        $this->addValidator('graph_output', fn ($out) => is_string($out) && in_array($out, ['string', 'file']));
        $this->addValidator('use_rrdcached', fn ($use) => is_bool($use));
        $this->addValidator('persistent_process', fn ($persistent) => is_bool($persistent));
        $this->addValidator('command_timeout', fn ($timeout) => is_int($timeout) && $timeout >= 0);
        $this->addValidator('rrdcached_address', fn ($address) => ! ($this->getBool('use_rrdcached') && empty($address)));
        $this->addValidator('default_step', fn ($step) => is_int($step) && $step > 0);
        $this->addValidator('default_archives', fn ($archives) => is_array($archives) && ! empty($archives));
        $this->addValidator('tag_strategy', function ($strategy) {
            return is_string($strategy) && class_exists($strategy) &&
                   is_subclass_of($strategy, RRDTagStrategyInterface::class);
        });

        parent::__construct($config);
    }

    /**
     * Get the tag strategy instance
     *
     * @throws ConfigurationException
     */
    public function getTagStrategy(): RRDTagStrategyInterface
    {
        $strategyClass = $this->getString('tag_strategy');
        $instance = new $strategyClass;

        if (! $instance instanceof RRDTagStrategyInterface) {
            throw new ConfigurationException('Invalid tag strategy class, must implement RRDTagStrategyInterface');
        }

        return $instance;
    }
}

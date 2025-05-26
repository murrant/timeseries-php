<?php

namespace TimeSeriesPhp\Drivers\RRDtool;

use TimeSeriesPhp\Config\AbstractConfig;
use TimeSeriesPhp\Drivers\RRDtool\Tags\FileNameStrategy;
use TimeSeriesPhp\Drivers\RRDtool\Tags\RRDTagStrategyContract;

class RRDtoolConfig extends AbstractConfig
{
    protected array $defaults = [
        'rrdtool_path' => 'rrdtool',
        'rrd_dir' => '/tmp/rrd',
        'use_rrdcached' => false,
        'rrdcached_address' => '',
        'default_step' => 300,
        'tag_strategy' => FileNameStrategy::class,
        'default_archives' => [
            'RRA:AVERAGE:0.5:1:2016',      // 5min for 1 week
            'RRA:AVERAGE:0.5:12:1488',     // 1hour for 2 months
            'RRA:AVERAGE:0.5:288:366',     // 1day for 1 year
            'RRA:MAX:0.5:1:2016',          // 5min max for 1 week
            'RRA:MAX:0.5:12:1488',         // 1hour max for 2 months
            'RRA:MIN:0.5:1:2016',          // 5min min for 1 week
            'RRA:MIN:0.5:12:1488'          // 1hour min for 2 months
        ],
    ];

    protected array $required = ['rrd_dir'];

    public function __construct(array $config = [])
    {
        $this->addValidator('rrdtool_path', function ($path) {
            return is_string($path) && !empty($path);
        });

        $this->addValidator('rrd_dir', function ($dir) {
            return is_string($dir) && !empty($dir);
        });

        $this->addValidator('use_rrdcached', function ($use) {
            return is_bool($use);
        });

        $this->addValidator('rrdcached_address', function ($address) {
            if ($this->get('use_rrdcached', false) && empty($address)) {
                return false;
            }
            return true;
        });

        $this->addValidator('default_step', function ($step) {
            return is_int($step) && $step > 0;
        });

        $this->addValidator('tag_strategy', function ($strategy) {
            return is_string($strategy) && class_exists($strategy) && 
                   is_subclass_of($strategy, RRDTagStrategyContract::class);
        });

        $this->addValidator('default_archives', function ($archives) {
            return is_array($archives) && !empty($archives);
        });

        parent::__construct($config);
    }

    /**
     * Get the tag strategy instance
     *
     * @return RRDTagStrategyContract
     */
    public function getTagStrategy(): RRDTagStrategyContract
    {
        $strategyClass = $this->get('tag_strategy');
        return new $strategyClass();
    }
}

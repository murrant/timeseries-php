<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Time Series Database Driver
    |--------------------------------------------------------------------------
    |
    | This option controls the default time series database driver that will be
    | used when storing and retrieving time series data. Supported drivers
    | are: "influxdb", "rrdtool", and "prometheus".
    |
    */
    'driver' => env('TIMESERIES_DRIVER', 'influxdb'),

    /*
    |--------------------------------------------------------------------------
    | Time Series Database Connections
    |--------------------------------------------------------------------------
    |
    | Here you may configure the connection information for each time series
    | database that will be used by your application. Examples of configuring
    | each supported driver are shown below.
    |
    */
    'drivers' => [
        'influxdb' => [
            'url' => env('INFLUXDB_URL', 'http://localhost:8086'),
            'token' => env('INFLUXDB_TOKEN', ''),
            'org' => env('INFLUXDB_ORG', ''),
            'bucket' => env('INFLUXDB_BUCKET', ''),
            'timeout' => env('INFLUXDB_TIMEOUT', 30),
            'verify_ssl' => env('INFLUXDB_VERIFY_SSL', true),
            'debug' => env('INFLUXDB_DEBUG', false),
            'precision' => env('INFLUXDB_PRECISION', 'ns'),
        ],

        'rrdtool' => [
            'rrdtool_path' => env('RRDTOOL_PATH', 'rrdtool'),
            'rrd_dir' => env('RRDTOOL_DIR', '/tmp/rrd'),
            'use_rrdcached' => env('RRDTOOL_USE_CACHED', false),
            'rrdcached_address' => env('RRDTOOL_CACHED_ADDRESS', ''),
            'default_step' => env('RRDTOOL_DEFAULT_STEP', 300),
            'tag_strategy' => env('RRDTOOL_TAG_STRATEGY', \TimeSeriesPhp\Drivers\RRDtool\Tags\FileNameStrategy::class),
            'default_archives' => [
                'RRA:AVERAGE:0.5:1:2016',      // 5min for 1 week
                'RRA:AVERAGE:0.5:12:1488',     // 1hour for 2 months
                'RRA:AVERAGE:0.5:288:366',     // 1day for 1 year
                'RRA:MAX:0.5:1:2016',          // 5min max for 1 week
                'RRA:MAX:0.5:12:1488',         // 1hour max for 2 months
                'RRA:MIN:0.5:1:2016',          // 5min min for 1 week
                'RRA:MIN:0.5:12:1488',          // 1hour min for 2 months
            ],
        ],

        'prometheus' => [
            // Placeholder for future Prometheus configuration
        ],
    ],
];

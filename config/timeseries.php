<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Time Series Database Driver
    |--------------------------------------------------------------------------
    |
    | This option controls the default time series database driver that will be
    | used when writing and querying time series data. You may set this to
    | any of the drivers defined in the "drivers" configuration array.
    |
    */
    'default' => env('TSDB_DEFAULT_DRIVER', 'influxdb'),

    /*
    |--------------------------------------------------------------------------
    | Time Series Database Drivers
    |--------------------------------------------------------------------------
    |
    | Here you may configure all of the time series database drivers for your
    | application. Examples have been provided for all available drivers.
    |
    */
    'drivers' => [
        'influxdb' => [
            'class' => \TimeSeriesPhp\Drivers\InfluxDB\InfluxDBDriver::class,
            'url' => env('INFLUXDB_URL', 'http://localhost:8086'),
            'token' => env('INFLUXDB_TOKEN', ''),
            'org' => env('INFLUXDB_ORG', ''),
            'bucket' => env('INFLUXDB_BUCKET', 'default'),
            'precision' => env('INFLUXDB_PRECISION', 'ns'),
        ],

        'prometheus' => [
            'class' => \TimeSeriesPhp\Drivers\Prometheus\PrometheusDriver::class,
            'url' => env('PROMETHEUS_URL', 'http://localhost:9090'),
        ],

        'rrdtool' => [
            'class' => \TimeSeriesPhp\Drivers\RRDtool\RRDtoolDriver::class,
            'data_dir' => env('RRDTOOL_DATA_DIR', storage_path('rrdtool')),
            'rrdtool_path' => env('RRDTOOL_PATH', '/usr/bin/rrdtool'),
            'rrdcached_socket' => env('RRDTOOL_CACHED_SOCKET', ''),
        ],

        'graphite' => [
            'class' => \TimeSeriesPhp\Drivers\Graphite\GraphiteDriver::class,
            'host' => env('GRAPHITE_HOST', 'localhost'),
            'port' => env('GRAPHITE_PORT', 2003),
            'protocol' => env('GRAPHITE_PROTOCOL', 'tcp'),
        ],

        'null' => [
            'class' => \TimeSeriesPhp\Drivers\Null\NullDriver::class,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Time Series Database Logging
    |--------------------------------------------------------------------------
    |
    | Here you may configure the logging behavior for the time series database.
    | By default, all queries and errors are logged to the default log channel.
    |
    */
    'logging' => [
        'enabled' => env('TSDB_LOGGING_ENABLED', true),
        'level' => env('TSDB_LOGGING_LEVEL', 'debug'),
    ],
];

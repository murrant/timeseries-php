<?php

return [
    'default' => env('TSDB_CONNECTION', 'test-influxdb2'),
    'default_write' => env('TSDB_WRITE_CONNECTION'),  // uses default connection if unset
    'metrics' => [
        'repository' => 'yaml',
        'path' => 'database/metrics.yaml',
    ],
    'connections' => [
        'all' => [
            'driver' => 'aggregate',
            'connections' => [
                'test-influxdb2',
                'test-rrd',
            ],
        ],
        'test-influxdb2' => [
            'driver' => 'influxdb2',
            'host' => 'localhost',
            'port' => 8086,
            'token' => 'my-token',
            'org' => 'my-org',
            'bucket' => 'example_bucket',
            'multiple_fields' => false,
        ],
        'test-rrd' => [
            'driver' => 'rrd',
            'dir' => env('RRD_DIR', 'database/rrd'),
            'rrdcached' => 'localhost:42217',
            'rrdtool_exec' => '/usr/bin/rrdtool',
            'default_retention_policies' => [
                ['name' => 'minute_for_two_days', 'resolution' => 60, 'retention' => 172800],
                ['name' => 'five_min_for_30_days', 'resolution' => 300, 'retention' => 2592000],
                ['name' => 'thirty_min_for_90_days', 'resolution' => 1800, 'retention' => 7776000],
                ['name' => 'two_hours_for_1_year', 'resolution' => 7200, 'retention' => 31536000],
                ['name' => 'one_day_for_2_years', 'resolution' => 86400, 'retention' => 63072000],
            ],
        ],
    ],
];

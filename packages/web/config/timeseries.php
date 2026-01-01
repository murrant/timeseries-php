<?php

return [
    'default' => env('TSDB_CONNECTION', 'test-influxdb2'),
    'metrics' => [
        'repository' => 'yaml',
        'path' => 'database/metrics.yaml',
    ],
    'connections' => [
        'test-influxdb2' => [
            'driver' => 'influxdb2',
            'host' => 'localhost',
            'port' => 8086,
            'token' => 'my-token',
            'org' => 'my-org',
            'bucket' => 'example_bucket',
        ],
        'test-rrd' => [
            'driver' => 'rrd',
            'dir' => env('RRD_DIR', 'database/rrd'),
            'rrdcached' => 'localhost:42217',
            'rrdtool_exec' => '/usr/bin/rrdtool',
            'default_retention_policies' => [
                ['resolution' => 60, 'retention' => 172800],     // 1 min for 2 days
                ['resolution' => 300, 'retention' => 2592000],   // 5 min for 30 days
                ['resolution' => 1800, 'retention' => 7776000],  // 30 min for 90 days
                ['resolution' => 7200, 'retention' => 31536000], // 2 hours for 1 year
                ['resolution' => 86400, 'retention' => 63072000], // 1 day for 2 years
            ],
        ],
    ],
];

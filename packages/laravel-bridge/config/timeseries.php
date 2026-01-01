<?php

return [
    'default' => env('TSDB_CONNECTION', 'metrics'),
    'metrics' => [
        'repository' => 'yaml',
        'path' => 'database/metrics.yaml',
    ],
    'connections' => [
        'metrics' => [
            'driver' => 'influxdb2',
            'host' => 'localhost',
            'port' => 8086,
            'token' => '',
            'org' => '',
            'bucket' => '',
        ],
        'null' => [
            'driver' => 'null',
        ],
        'rrd' => [
            'driver' => 'rrd',
            'path' => env('RRD_DIR', 'database/rrd'),
        ],
    ],
];

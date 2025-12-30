<?php

return [
    'default' => 'metrics',
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
    ],
];

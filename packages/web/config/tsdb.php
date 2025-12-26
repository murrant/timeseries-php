<?php

return [
    'driver' => 'influxdb2',
    'graphs' => [
        'repository' => 'yaml',
        'path' => 'database/graphs',
    ],
    'metrics' => [
        'repository' => 'yaml',
        'path' => 'database/metrics.yaml',
    ],
    'drivers' => [
        'influxdb2' => [
            'host' => 'localhost',
            'port' => 8086,
            'token' => 'my-token',
            'org' => 'my-org',
            'bucket' => 'example_bucket',
        ],
    ],
];

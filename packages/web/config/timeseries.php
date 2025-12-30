<?php

return [
    'default' => 'test',
    'metrics' => [
        'repository' => 'yaml',
        'path' => 'database/metrics.yaml',
    ],
    'connections' => [
        'test' => [
            'driver' => 'influxdb2',
            'host' => 'localhost',
            'port' => 8086,
            'token' => 'my-token',
            'org' => 'my-org',
            'bucket' => 'example_bucket',
        ],
    ],
];

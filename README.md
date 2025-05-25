# timeseries-php

A library to abstract storing data in and retrieving data from timeseries databases.

## Installation
`composer require librenms/timeseries-php`

## Usage
```php
try {
    // InfluxDB example
    $influxdb = TSDBFactory::create('influxdb', [
        'host' => 'localhost',
        'port' => 8086,
        'database' => 'mydb'
    ]);

    // RRDtool example
    $rrdtool = TSDBFactory::create('rrdtool', [
        'rrd_dir' => '/var/lib/rrd'
    ]);

    // Write data (works the same across all drivers)
    $dataPoint = new DataPoint('cpu_usage', ['value' => 85.5, 'load' => 1.2])
        ->addTag('host', 'server1')
        ->addTag('region', 'us-west');

    $rrdtool->write($dataPoint);

    // Query data
    $query = (new Query('cpu_usage'))
        ->select(['value', 'load'])
        ->where('host', 'server1')
        ->timeRange(
            new DateTime('-1 hour'),
            new DateTime()
        )
        ->aggregate('average')
        ->limit(100);

    $result = $rrdtool->query($query);

    foreach ($result->getSeries() as $point) {
        echo "Time: {$point['time']}, Value: {$point['value']}, Load: {$point['load']}\n";
    }

    // RRDtool-specific: Create custom RRD
    $rrdtool->createRRDWithCustomConfig('network_traffic', ['interface' => 'eth0'], [
        'step' => 60,
        'data_sources' => [
            'DS:rx_bytes:COUNTER:120:0:U',
            'DS:tx_bytes:COUNTER:120:0:U'
        ],
        'archives' => [
            'RRA:AVERAGE:0.5:1:1440',  // 1min for 1 day
            'RRA:AVERAGE:0.5:60:168',  // 1hour for 1 week
            'RRA:MAX:0.5:1:1440'       // 1min max for 1 day
        ]
    ]);

    // RRDtool-specific: Generate graph
    $graphPath = $rrdtool->getRRDGraph('cpu_usage', ['host' => 'server1'], [
        'start' => '-1d',
        'end' => 'now',
        'width' => 800,
        'height' => 400,
        'title' => 'CPU Usage - Server1',
        'vertical-label' => 'Percentage',
        'def' => 'value=' . $rrdtool->getRRDPath('cpu_usage', ['host' => 'server1']) . ':value:AVERAGE',
        'line1' => 'value#FF0000:CPU Usage'
    ]);

    // Raw RRDtool command
    $rawResult = $rrdtool->rawQuery('rrdtool fetch /var/lib/rrd/cpu_usage_host-server1.rrd AVERAGE -s -3600');

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
```

## Testing
This project uses PHPUnit for testing. To run the tests:

```bash
# Install dependencies
composer install

# Run all tests
./vendor/bin/phpunit

# Run specific test file
./vendor/bin/phpunit tests/Core/QueryTest.php

# Run tests with coverage report
./vendor/bin/phpunit --coverage-html coverage
```

The test suite includes:
- Unit tests for core components (Query, DataPoint, QueryResult)
- Tests for configuration classes
- Tests for the factory class
- Tests for database drivers using mocks

## Contributing
Open PRs ;)

## Authors and acknowledgment
Tony Murray & LibreNMS Contributors

## License
MIT

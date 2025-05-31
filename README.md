<img src="docs/images/timeseries-php-logo.svg" alt="timeseries-php logo" width="160"/>

# 📊 timeseries-php

A library to abstract storing data in and retrieving data from timeseries databases.

![check-code-coverage](https://img.shields.io/badge/code--coverage-80%25-brightgreen)

## 📥 Installation
`composer require librenms/timeseries-php`

## ⚙️ Laravel Integration

This library includes Laravel integration. After installing the package, Laravel will automatically discover the service provider and register the facade.

### 📤 Publishing the Configuration

To publish the configuration file, run:

```bash
php artisan vendor:publish --tag=time-series-config
```

This will create a `config/time-series.php` file in your Laravel application.

### ⚙️ Configuration

The configuration file includes options for all supported drivers:

```php
// config/time-series.php
return [
    // Default driver to use
    'driver' => env('TIMESERIES_DRIVER', 'rrdtool'),

    // Driver-specific configurations
    'drivers' => [
        'influxdb' => [
            'url' => env('INFLUXDB_URL', 'http://localhost:8086'),
            'token' => env('INFLUXDB_TOKEN', ''),
            'org' => env('INFLUXDB_ORG', ''),
            'bucket' => env('INFLUXDB_BUCKET', ''),
            // Additional options...
        ],

        'rrdtool' => [
            'rrdtool_path' => env('RRDTOOL_PATH', 'rrdtool'),
            'rrd_dir' => env('RRDTOOL_DIR', '/tmp/rrd'),
            // Additional options...
        ],

        // Other drivers...
    ],
];
```

### 🚀 Usage with Laravel

You can use the `TimeSeries` facade to access the time series functionality:

```php
use TimeSeriesPhp\Support\TimeSeriesFacade as TimeSeries;

// Write data
$dataPoint = new DataPoint('cpu_usage', ['value' => 85.5])
    ->addTag('host', 'server1');

TimeSeries::write($dataPoint);

// Query data
$query = (new Query('cpu_usage'))
    ->select(['value'])
    ->where('host', 'server1')
    ->timeRange(
        now()->subHour(),
        now()
    );

$result = TimeSeries::query($query);
```

## 💻 Direct Usage

### 🔧 Symfony Container and Configuration

This library uses Symfony's DependencyInjection and Config components for service management and configuration. The configuration is defined in YAML files in the `config` directory.

#### Basic Configuration Structure

```yaml
# config/packages/config.yaml
# Default driver to use when none is specified
default_driver: 'influxdb'

# Configuration for different drivers
drivers:
    influxdb:
        url: 'http://localhost:8086'
        token: ''
        org: ''
        bucket: 'default'
        precision: 'ns'

    prometheus:
        url: 'http://localhost:9090'
```

#### Using the Container

```php
use TimeSeriesPhp\Core\ContainerFactory;
use TimeSeriesPhp\Services\ConfigurationManager;

// Create the container
$container = ContainerFactory::create();

// Get the configuration manager
$configManager = $container->get(ConfigurationManager::class);

// Get configuration values
$defaultDriver = $configManager->get('default_driver');
$influxdbConfig = $configManager->getDriverConfig('influxdb');
```

### 🚀 Using Drivers Directly

```php
try {
    // InfluxDB example
    $influxdb = DriverManager::create('influxdb', [
        'host' => 'localhost',
        'port' => 8086,
        'database' => 'mydb'
    ]);

    // RRDtool example
    $rrdtool = DriverManager::create('rrdtool', [
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

## 🧪 Testing
This project uses PHPUnit for testing. To run the tests:

```bash
# Install dependencies
composer install

# Run all tests (excluding integration tests by default)
./vendor/bin/phpunit

# Run specific test file
./vendor/bin/phpunit tests/Core/QueryTest.php

# Run tests with coverage report
./vendor/bin/phpunit --coverage-html coverage

# Run all tests including integration tests
./vendor/bin/phpunit --group=integration

# Start docker containers and run integration tests
./docker/run_integration_tests.sh
```

The test suite includes:
- ✅ Unit tests for core components (Query, DataPoint, QueryResult)
- ✅ Tests for configuration classes
- ✅ Tests for the factory class
- ✅ Tests for database drivers using mocks
- ✅ Integration tests for each database driver
- ⚡ Benchmark tests for performance evaluation

### 🔄 Integration and Benchmark Tests

To run integration and benchmark tests, you need to set up the time series databases first. See [docs/TSDB_SETUP.md](docs/TSDB_SETUP.md) for detailed instructions on how to set up each database for testing.

#### 🐳 Running Integration Tests with Docker Compose

The easiest way to run integration tests is to use the provided script that automatically starts Docker Compose, runs the tests, and then stops Docker Compose:

```bash
# Run all integration tests with Docker Compose
./docker/run-integration-tests.sh
```

#### 🖥️ Running Tests Manually

If you prefer to run the tests manually:

```bash
# Run all integration tests
./vendor/bin/phpunit --group integration

# Run all benchmark tests
./vendor/bin/phpunit --group benchmark
```

For more information about testing, see [docs/TESTING.md](docs/TESTING.md).

## 🤝 Contributing
Open PRs ;)

## 👏 Authors and acknowledgment
Tony Murray & Contributors

## 📜 License
MIT

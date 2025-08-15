# TimeSeriesPhp Laravel Integration

This package provides Laravel integration for the TimeSeriesPhp library, allowing you to easily work with time series databases in your Laravel applications.

## Installation

You can install the package via composer:

```bash
composer require timeseries-php/laravel
```

## Configuration

Publish the configuration file:

```bash
php artisan vendor:publish --tag=timeseries-config
```

This will create a `config/timeseries.php` file in your application. You can configure the default driver and driver-specific settings in this file.

## Usage

### Basic Usage

You can use the `TSDB` facade to interact with the time series database:

```php
use TimeSeriesPhp\Laravel\Facades\TSDB;

// Write a data point
TSDB::write('cpu_usage', ['value' => 45.2], ['host' => 'server1']);

// Query the last value
$result = TSDB::queryLast('cpu_usage', 'value', ['host' => 'server1']);
```

### Dependency Injection

You can also inject the TSDB instance into your classes:

```php
use TimeSeriesPhp\TSDB;

class MetricsController extends Controller
{
    protected TSDB $tsdb;

    public function __construct(TSDB $tsdb)
    {
        $this->tsdb = $tsdb;
    }

    public function store(Request $request)
    {
        $this->tsdb->write('cpu_usage', ['value' => $request->value], ['host' => $request->host]);

        return response()->json(['status' => 'success']);
    }
}
```

### Available Methods

The `TSDB` facade provides the following methods:

- `write(string $measurement, array $fields, array $tags = [], ?DateTime $timestamp = null): bool`
- `writeBatch(array $dataPoints): bool`
- `query(Query $query): QueryResult`
- `queryLast(string $measurement, string $field, array $tags = []): QueryResult`
- `queryFirst(string $measurement, string $field, array $tags = []): QueryResult`
- `queryAvg(string $measurement, string $field, DateTime $start, DateTime $end, array $tags = []): QueryResult`
- `querySum(string $measurement, string $field, DateTime $start, DateTime $end, array $tags = []): QueryResult`
- `queryCount(string $measurement, string $field, DateTime $start, DateTime $end, array $tags = []): QueryResult`
- `queryMin(string $measurement, string $field, DateTime $start, DateTime $end, array $tags = []): QueryResult`
- `queryMax(string $measurement, string $field, DateTime $start, DateTime $end, array $tags = []): QueryResult`
- `deleteMeasurement(string $measurement, ?DateTime $start = null, ?DateTime $stop = null): bool`
- `getSchemaManager(): SchemaManagerInterface`
- `close(): void`

### Switching Drivers

You can switch between different drivers at runtime:

```php
use TimeSeriesPhp\TSDB;

// Create a new instance with a specific driver
$influxdb = new TSDB('influxdb', ['url' => 'http://localhost:8086']);
$prometheus = new TSDB('prometheus', ['url' => 'http://localhost:9090']);

// Or use the app container to resolve a specific driver
$influxdb = app()->make(TSDB::class, ['driver' => 'influxdb']);
```

## Available Drivers

The package comes with the following drivers:

- `influxdb`: InfluxDB driver
- `prometheus`: Prometheus driver
- `rrdtool`: RRDtool driver
- `graphite`: Graphite driver
- `null`: Null driver (for testing)

## Adding Custom Drivers

You can add custom drivers by extending the configuration:

```php
// In a service provider
$this->app['config']->set('timeseries.drivers.custom', [
    'class' => \App\TimeSeriesDrivers\CustomDriver::class,
    // Driver-specific configuration
]);
```

## License

This package is open-sourced software licensed under the MIT license.

# Working with Data Points in TimeSeriesPhp

This document provides detailed information about working with data points in TimeSeriesPhp.

## Introduction

In time series databases, a data point represents a single measurement at a specific point in time. In TimeSeriesPhp, data points are represented by the `DataPoint` class, which encapsulates all the information needed to store a measurement.

## The DataPoint Class

The `DataPoint` class is the core class for representing time series data in TimeSeriesPhp.

### Creating a Data Point

```php
__construct(string $measurement, array $fields, array $tags = [], ?DateTime $timestamp = null)
```

Creates a new data point.

#### Parameters

- `$measurement` (string): The name of the measurement (e.g., 'cpu_usage')
- `$fields` (array): An associative array of field names and values (e.g., ['value' => 85.5])
- `$tags` (array, optional): An associative array of tag names and values (e.g., ['host' => 'server1'])
- `$timestamp` (DateTime, optional): The timestamp of the data point. If not provided, the current time will be used.

#### Examples

```php
// Basic data point with just a measurement and fields
$dataPoint = new DataPoint('cpu_usage', ['value' => 85.5]);

// Data point with tags
$dataPoint = new DataPoint('cpu_usage', ['value' => 85.5], ['host' => 'server1']);

// Data point with a specific timestamp
$dataPoint = new DataPoint(
    'cpu_usage',
    ['value' => 85.5],
    ['host' => 'server1'],
    new DateTime('2023-01-01 12:00:00')
);
```

### Adding Tags

```php
addTag(string $key, string $value): self
```

Adds a tag to the data point.

#### Parameters

- `$key` (string): The tag name
- `$value` (string): The tag value

#### Returns

- `self`: The data point instance (for method chaining)

#### Examples

```php
$dataPoint = new DataPoint('cpu_usage', ['value' => 85.5]);
$dataPoint->addTag('host', 'server1')
          ->addTag('region', 'us-west')
          ->addTag('environment', 'production');
```

### Adding Fields

```php
addField(string $key, mixed $value): self
```

Adds a field to the data point.

#### Parameters

- `$key` (string): The field name
- `$value` (mixed): The field value

#### Returns

- `self`: The data point instance (for method chaining)

#### Examples

```php
$dataPoint = new DataPoint('system_metrics', []);
$dataPoint->addField('cpu_usage', 85.5)
          ->addField('memory_usage', 75.2)
          ->addField('disk_usage', 60.8);
```

### Getting Data Point Information

```php
getMeasurement(): string
getTags(): array
getFields(): array
getTimestamp(): ?DateTime
```

These methods allow you to retrieve information about the data point.

#### Returns

- `getMeasurement()`: The measurement name
- `getTags()`: An associative array of tags
- `getFields()`: An associative array of fields
- `getTimestamp()`: The timestamp of the data point, or null if not set

#### Examples

```php
$dataPoint = new DataPoint(
    'cpu_usage',
    ['value' => 85.5],
    ['host' => 'server1'],
    new DateTime('2023-01-01 12:00:00')
);

$measurement = $dataPoint->getMeasurement(); // 'cpu_usage'
$tags = $dataPoint->getTags();               // ['host' => 'server1']
$fields = $dataPoint->getFields();           // ['value' => 85.5]
$timestamp = $dataPoint->getTimestamp();     // DateTime object
```

## Writing Data Points

Once you have created a data point, you can write it to a time series database using the `write()` method of a database driver instance.

### Writing a Single Data Point

```php
$db = TSDBFactory::create('influxdb', $config);
$dataPoint = new DataPoint('cpu_usage', ['value' => 85.5], ['host' => 'server1']);
$success = $db->write($dataPoint);
```

### Writing Multiple Data Points

For better performance when writing multiple data points, use the `writeBatch()` method:

```php
$db = TSDBFactory::create('influxdb', $config);

$dataPoints = [
    new DataPoint('cpu_usage', ['value' => 85.5], ['host' => 'server1']),
    new DataPoint('memory_usage', ['value' => 75.2], ['host' => 'server1']),
    new DataPoint('disk_usage', ['value' => 60.8], ['host' => 'server1']),
];

$success = $db->writeBatch($dataPoints);
```

## Best Practices

### Tag vs. Field Selection

When deciding whether to use a tag or a field for a particular piece of data, consider the following:

- **Tags** are indexed and are used for filtering data. Use tags for metadata that you will frequently filter by (e.g., host, region, environment).
- **Fields** are not indexed and are used for storing the actual measurements. Use fields for the values you want to analyze (e.g., CPU usage, memory usage, temperature).

### Timestamp Precision

Be consistent with timestamp precision across your application. If you don't specify a timestamp, TimeSeriesPhp will use the current time with microsecond precision.

### Batch Writing

When writing large amounts of data, use the `writeBatch()` method instead of calling `write()` multiple times. This can significantly improve performance by reducing the number of network requests.

### Memory Management

When working with large datasets, be mindful of memory usage. If you need to write a very large number of data points, consider breaking them into smaller batches:

```php
$batchSize = 1000;
$dataPoints = [];

for ($i = 0; $i < 10000; $i++) {
    $dataPoints[] = new DataPoint(
        'cpu_usage',
        ['value' => rand(0, 100)],
        ['host' => "server{$i % 100}"]
    );
    
    // Write in batches of 1000
    if (count($dataPoints) >= $batchSize) {
        $db->writeBatch($dataPoints);
        $dataPoints = []; // Clear the array
    }
}

// Write any remaining data points
if (!empty($dataPoints)) {
    $db->writeBatch($dataPoints);
}
```

## Driver-Specific Considerations

Different time series database drivers may have specific requirements or optimizations for data points:

### InfluxDB

- Tags and field keys are case-sensitive
- Field values can be float, integer, string, or boolean
- Tag values are always stored as strings

### Prometheus

- Prometheus is primarily a pull-based system, so writing data points may work differently
- Metric names must follow Prometheus naming conventions
- Labels (tags) should be used sparingly

### Graphite

- Measurement names often use dot notation (e.g., 'servers.server1.cpu_usage')
- Tags are not natively supported in older versions of Graphite

### RRDtool

- RRDtool requires pre-defined data sources and round robin archives
- Data points must match the expected data source names

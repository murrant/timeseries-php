# TimeSeriesPhp API Documentation

This document provides a comprehensive guide to the TimeSeriesPhp package API for consumers.

## Table of Contents

1. [Introduction](#introduction)
2. [Installation](#installation)
3. [Basic Usage](#basic-usage)
4. [Factory](#factory)
5. [Data Points](#data-points)
6. [Querying](#querying)
7. [Database Management](#database-management)
8. [Error Handling](#error-handling)
9. [Drivers](#drivers)
10. [Configuration](#configuration)

## Introduction

TimeSeriesPhp is a PHP library for working with time series databases. It provides a unified interface for interacting with various time series database systems, including InfluxDB, Prometheus, Graphite, and RRDtool.

## Installation

```bash
composer require librenms/timeseries-php
```

## Basic Usage

### With Explicit Configuration

```php
<?php

use TimeSeriesPhp\Core\DriverManager;
use TimeSeriesPhp\Core\DataPoint;
use TimeSeriesPhp\Core\Query;
use TimeSeriesPhp\Drivers\InfluxDB\InfluxDBConfig;

// Create a configuration
$config = new InfluxDBConfig([
    'url' => 'http://localhost:8086',
    'token' => 'your-token',
    'org' => 'your-org',
    'bucket' => 'your-bucket',
]);

// Create a database instance with explicit config
$db = DriverManager::create('influxdb', $config);

// Write a data point
$dataPoint = new DataPoint(
    'cpu_usage',
    ['value' => 85.5],
    ['host' => 'server1']
);
$db->write($dataPoint);

// Query data
$query = new Query('cpu_usage');
$query->select(['value'])
      ->where('host', '=', 'server1')
      ->timeRange(new DateTime('-1 hour'), new DateTime());
$result = $db->query($query);

// Close the connection
$db->close();
```

### With Default Configuration

```php
<?php

use TimeSeriesPhp\Core\DriverManager;
use TimeSeriesPhp\Core\DataPoint;
use TimeSeriesPhp\Core\Query;

// Create a database instance with default config
// Note: Default config may not have all required settings for your environment
$db = DriverManager::create('influxdb');

// The rest of the usage is the same
// ...
```

## Factory

The `DriverManager` class is the main entry point for creating database instances.

### Methods

#### `registerDriver(string $name, string $className, ?string $configClassName = null): void`

Registers a driver with the factory. If `$configClassName` is not provided, it will be inferred from the driver class name by replacing "Driver" with "Config".

```php
// With explicit config class
DriverManager::register('custom', CustomDriver::class, CustomConfig::class);

// With inferred config class (CustomConfig will be inferred from CustomDriver)
DriverManager::register('custom', CustomDriver::class);
```

#### `unregisterDriver(string $name): bool`

Unregisters a driver from the factory.

```php
DriverManager::unregister('custom');
```

#### `create(string $driver, ?ConfigInterface $config = null, bool $autoConnect = true): TimeSeriesInterface`

Creates a new instance of a time series database driver. If `$config` is not provided, a default configuration will be created using the registered config class for the driver.

```php
// With explicit config
$db = DriverManager::create('influxdb', $config);

// With default config
$db = DriverManager::create('influxdb');
```

#### `getAvailableDrivers(): array`

Gets a list of all registered drivers.

```php
$drivers = DriverManager::getAvailableDrivers();
```

#### `hasDriver(string $name): bool`

Checks if a driver is registered.

```php
if (DriverManager::hasDriver('influxdb')) {
    // Use InfluxDB driver
}
```

#### `getConfigClass(string $name): ?string`

Gets the config class for a driver.

```php
$configClass = DriverManager::getConfigClass('influxdb');
if ($configClass) {
    $config = new $configClass([/* config options */]);
}
```

## Data Points

The `DataPoint` class represents a single data point in a time series.

### Methods

#### `__construct(string $measurement, array $fields, array $tags = [], ?DateTime $timestamp = null)`

Creates a new data point.

```php
$dataPoint = new DataPoint(
    'cpu_usage',
    ['value' => 85.5],
    ['host' => 'server1'],
    new DateTime()
);
```

#### `addTag(string $key, string $value): self`

Adds a tag to the data point.

```php
$dataPoint->addTag('region', 'us-west');
```

#### `addField(string $key, mixed $value): self`

Adds a field to the data point.

```php
$dataPoint->addField('temperature', 72.5);
```

#### Getters

```php
$measurement = $dataPoint->getMeasurement();
$tags = $dataPoint->getTags();
$fields = $dataPoint->getFields();
$timestamp = $dataPoint->getTimestamp();
```

## Querying

The `Query` class provides a fluent interface for building queries.

### Basic Query Methods

```php
$query = new Query('cpu_usage');
$query->select(['value', 'usage_user', 'usage_system'])
      ->where('host', '=', 'server1')
      ->timeRange(new DateTime('-1 hour'), new DateTime())
      ->orderByTime('DESC')
      ->limit(100);
```

### Time Range Methods

```php
// Specific time range
$query->timeRange(new DateTime('-1 hour'), new DateTime());

// Since a specific time
$query->since(new DateTime('-1 day'));

// Until a specific time
$query->until(new DateTime());

// Latest data
$query->latest('1h'); // Last hour
```

### Filtering Methods

```php
// Basic conditions
$query->where('host', '=', 'server1');
$query->orWhere('host', '=', 'server2');

// Advanced filtering
$query->whereIn('region', ['us-west', 'us-east']);
$query->whereNotIn('service', ['nginx', 'apache']);
$query->whereBetween('value', 10, 90);
$query->whereRegex('host', '^web-.*');
```

### Aggregation Methods

```php
// Group by time
$query->groupByTime('5m');

// Group by tags
$query->groupBy(['host', 'region'], '5m');

// Aggregation functions
$query->sum('value', 'total');
$query->avg('value', 'average');
$query->count('value', 'count');
$query->min('value', 'minimum');
$query->max('value', 'maximum');
$query->first('value', 'first');
$query->last('value', 'last');
$query->percentile('value', 95, 'p95');
$query->stddev('value', 'std_dev');
```

### Fill Methods

```php
$query->fillNull();      // Fill with null
$query->fillNone();      // No filling
$query->fillPrevious();  // Fill with previous value
$query->fillLinear();    // Linear interpolation
$query->fillValue(0);    // Fill with a specific value
```

## Database Management

### Creating a Database

```php
$db->createDatabase('new_database');
```

### Deleting a Database

```php
$db->deleteDatabase('old_database');
```

### Listing Databases

```php
$databases = $db->getDatabases();
```

### Deleting a Measurement

```php
// Delete all data in a measurement
$db->deleteMeasurement('cpu_usage');

// Delete data in a specific time range
$db->deleteMeasurement('cpu_usage', new DateTime('-1 day'), new DateTime());
```

## Error Handling

The package uses exceptions for error handling. All exceptions extend the base `TSDBException` class.

```php
use TimeSeriesPhp\Exceptions\TSDBException;
use TimeSeriesPhp\Exceptions\ConnectionException;
use TimeSeriesPhp\Exceptions\QueryException;
use TimeSeriesPhp\Exceptions\WriteException;
use TimeSeriesPhp\Exceptions\DatabaseException;

try {
    $db = DriverManager::create('influxdb', $config);
    $db->write($dataPoint);
    $result = $db->query($query);
} catch (ConnectionException $e) {
    // Handle connection errors
    echo "Connection error: " . $e->getMessage();
} catch (WriteException $e) {
    // Handle write errors
    echo "Write error: " . $e->getMessage();
} catch (QueryException $e) {
    // Handle query errors
    echo "Query error: " . $e->getMessage();
} catch (DatabaseException $e) {
    // Handle database management errors
    echo "Database error: " . $e->getMessage();
} catch (TSDBException $e) {
    // Handle other errors
    echo "Error: " . $e->getMessage();
}
```

## Drivers

The package includes drivers for several time series databases:

- InfluxDB
- Prometheus
- Graphite
- RRDtool

Each driver has its own configuration class that extends the base `ConfigInterface`.

## Configuration

Each driver has its own configuration requirements. Here are examples for the included drivers:

### InfluxDB Configuration

```php
use TimeSeriesPhp\Drivers\InfluxDB\InfluxDBConfig;

$config = new InfluxDBConfig([
    'url' => 'http://localhost:8086',
    'token' => 'your-token',
    'org' => 'your-org',
    'bucket' => 'your-bucket',
]);
```

### Prometheus Configuration

```php
use TimeSeriesPhp\Drivers\Prometheus\PrometheusConfig;

$config = new PrometheusConfig([
    'url' => 'http://localhost:9090',
    'username' => 'your-username',
    'password' => 'your-password',
]);
```

### Graphite Configuration

```php
use TimeSeriesPhp\Drivers\Graphite\GraphiteConfig;

$config = new GraphiteConfig([
    'host' => 'localhost',
    'port' => 2003,
    'protocol' => 'tcp',
]);
```

### RRDtool Configuration

```php
use TimeSeriesPhp\Drivers\RRDtool\RRDtoolConfig;

$config = new RRDtoolConfig([
    'path' => '/path/to/rrd/files',
    'rrdtool_bin' => '/usr/bin/rrdtool',
]);
```

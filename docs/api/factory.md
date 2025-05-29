# TimeSeriesPhp Factory API

This document provides detailed information about the factory pattern used in TimeSeriesPhp to create database driver instances.

## Introduction

The `TSDBFactory` class is the main entry point for creating database instances in TimeSeriesPhp. It provides a simple and consistent way to instantiate different database drivers without having to know the details of their implementation.

## Factory Methods

### Creating a Database Instance

```php
TSDBFactory::create(string $driver, ?ConfigInterface $config = null, bool $autoConnect = true): TimeSeriesInterface
```

Creates a new instance of a time series database driver.

#### Parameters

- `$driver` (string): The name of the driver to create (e.g., 'influxdb', 'prometheus', 'graphite', 'rrdtool')
- `$config` (ConfigInterface, optional): Configuration for the driver. If not provided, a default configuration will be created.
- `$autoConnect` (bool, default: true): Whether to automatically connect to the database after creating the instance.

#### Returns

- `TimeSeriesInterface`: An instance of the requested database driver.

#### Exceptions

- `DriverNotFoundException`: If the requested driver is not registered.
- `ConfigurationException`: If the provided configuration is invalid.
- `ConnectionException`: If `$autoConnect` is true and the connection fails.

#### Examples

```php
// With explicit configuration
$config = new InfluxDBConfig([
    'url' => 'http://localhost:8086',
    'token' => 'your-token',
    'org' => 'your-org',
    'bucket' => 'your-bucket',
]);
$db = TSDBFactory::create('influxdb', $config);

// With default configuration
$db = TSDBFactory::create('influxdb');

// Without auto-connecting
$db = TSDBFactory::create('influxdb', $config, false);
$db->connect(); // Connect manually
```

### Registering a Custom Driver

```php
TSDBFactory::registerDriver(string $name, string $driverClassName, ?string $configClassName = null): void
```

Registers a driver with the factory.

#### Parameters

- `$name` (string): The name to register the driver under.
- `$driverClassName` (string): The fully qualified class name of the driver.
- `$configClassName` (string, optional): The fully qualified class name of the driver's configuration class. If not provided, it will be inferred from the driver class name by replacing "Driver" with "Config".

#### Examples

```php
// With explicit config class
TSDBFactory::registerDriver('custom', CustomDriver::class, CustomConfig::class);

// With inferred config class (CustomConfig will be inferred from CustomDriver)
TSDBFactory::registerDriver('custom', CustomDriver::class);
```

### Unregistering a Driver

```php
TSDBFactory::unregisterDriver(string $name): bool
```

Unregisters a driver from the factory.

#### Parameters

- `$name` (string): The name of the driver to unregister.

#### Returns

- `bool`: True if the driver was unregistered, false if it wasn't registered.

#### Examples

```php
TSDBFactory::unregisterDriver('custom');
```

### Getting Available Drivers

```php
TSDBFactory::getAvailableDrivers(): array
```

Gets a list of all registered drivers.

#### Returns

- `array`: An array of driver names.

#### Examples

```php
$drivers = TSDBFactory::getAvailableDrivers();
foreach ($drivers as $driver) {
    echo "Available driver: $driver\n";
}
```

### Checking if a Driver is Available

```php
TSDBFactory::hasDriver(string $name): bool
```

Checks if a driver is registered.

#### Parameters

- `$name` (string): The name of the driver to check.

#### Returns

- `bool`: True if the driver is registered, false otherwise.

#### Examples

```php
if (TSDBFactory::hasDriver('influxdb')) {
    // Use InfluxDB driver
} else {
    // Use fallback driver
}
```

### Getting a Driver's Config Class

```php
TSDBFactory::getConfigClass(string $name): ?string
```

Gets the config class for a driver.

#### Parameters

- `$name` (string): The name of the driver.

#### Returns

- `string|null`: The fully qualified class name of the driver's configuration class, or null if the driver is not registered.

#### Examples

```php
$configClass = TSDBFactory::getConfigClass('influxdb');
if ($configClass) {
    $config = new $configClass([/* config options */]);
    $db = TSDBFactory::create('influxdb', $config);
}
```

## Creating a Custom Driver

To create a custom driver for TimeSeriesPhp, you need to:

1. Create a driver class that implements `TimeSeriesInterface` or extends `AbstractTimeSeriesDB`
2. Create a configuration class that implements `ConfigInterface`
3. Register your driver with the factory

### Example: Custom Driver Implementation

```php
<?php

namespace MyApp\TimeSeriesPhp\Drivers\Custom;

use TimeSeriesPhp\Core\AbstractTimeSeriesDB;
use TimeSeriesPhp\Core\DataPoint;
use TimeSeriesPhp\Core\Query;
use TimeSeriesPhp\Core\QueryResult;

class CustomDriver extends AbstractTimeSeriesDB
{
    public function connect(): bool
    {
        // Implement connection logic
        return true;
    }

    public function close(): bool
    {
        // Implement close logic
        return true;
    }

    public function write(DataPoint $dataPoint): bool
    {
        // Implement write logic
        return true;
    }

    public function writeBatch(array $dataPoints): bool
    {
        // Implement batch write logic
        return true;
    }

    public function query(Query $query): QueryResult
    {
        // Implement query logic
        return new QueryResult([]);
    }

    // Implement other required methods...
}
```

### Example: Custom Configuration Class

```php
<?php

namespace MyApp\TimeSeriesPhp\Drivers\Custom;

use TimeSeriesPhp\Contracts\Config\ConfigInterface;

class CustomConfig implements ConfigInterface
{
    private array $options;

    public function __construct(array $options = [])
    {
        $this->options = array_merge([
            // Default options
            'host' => 'localhost',
            'port' => 1234,
        ], $options);
    }

    public function getOption(string $name, $default = null)
    {
        return $this->options[$name] ?? $default;
    }

    public function setOption(string $name, $value): self
    {
        $this->options[$name] = $value;
        return $this;
    }

    public function toArray(): array
    {
        return $this->options;
    }
}
```

### Example: Registering the Custom Driver

```php
<?php

use TimeSeriesPhp\Core\TSDBFactory;
use MyApp\TimeSeriesPhp\Drivers\Custom\CustomDriver;
use MyApp\TimeSeriesPhp\Drivers\Custom\CustomConfig;

// Register the custom driver
TSDBFactory::registerDriver('custom', CustomDriver::class, CustomConfig::class);

// Create an instance of the custom driver
$config = new CustomConfig([
    'host' => 'custom-host',
    'port' => 5678,
]);
$db = TSDBFactory::create('custom', $config);
```

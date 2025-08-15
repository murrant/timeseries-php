# Laravel Integration Tests

This directory contains tests for the Laravel integration of the TimeSeriesPhp library.

## Overview

The Laravel integration provides the following features:

1. A service provider (`TimeSeriesServiceProvider`) that registers the TSDB class and its dependencies
2. A facade (`TSDB`) for easy access to the TSDB functionality
3. Configuration loading from Laravel's config system

## Test Structure

The tests are organized as follows:

- `LaravelTestCase.php`: Base test case for Laravel integration tests
- `TimeSeriesServiceProviderTest.php`: Tests for the service provider registration
- `ConfigurationTest.php`: Tests for configuration loading and driver resolution
- `Facades/TSDBFacadeTest.php`: Tests for the TSDB facade functionality

## Running the Tests

To run the Laravel integration tests:

```bash
./vendor/bin/phpunit tests/Laravel
```

## Test Coverage

The tests cover the following aspects of the Laravel integration:

### Service Provider Registration

- Registration of the DriverFactory
- Registration of the TSDB class
- Registration of the 'timeseries' alias
- Support for different drivers (InfluxDB, Null, etc.)

### Configuration Loading

- Loading the default driver from configuration
- Customizing the default driver
- Passing driver-specific configuration to the driver factory
- Accessing and updating logging configuration

### Facade Functionality

- Writing data points
- Writing batches of data points
- Querying data
- Querying specific data (last, first, etc.)

## Dependencies

The Laravel integration tests require the following dependencies:

- illuminate/container
- illuminate/config
- illuminate/support
- mockery/mockery
- phpoption/phpoption

These are added as dev dependencies in the composer.json file.

## Issues and Considerations

During the implementation of these tests, we encountered a few issues that are worth noting:

1. **Environment Variable Handling**: Laravel's `env()` function in the configuration file requires the `vlucas/phpdotenv` package. For testing purposes, we avoided loading the actual configuration file and instead created a mock configuration.

2. **Config Repository vs. Array**: Laravel's service provider expects a Config Repository object rather than a simple array. We had to use `Illuminate\Config\Repository` instead of an array for the configuration.

3. **Facade Setup**: The Laravel Facade system requires proper setup with `Facade::setFacadeApplication()` to work correctly in tests.

4. **Container Mocking**: Rather than trying to mock the entire Laravel application, we used a simplified approach with a real Container and mocked only the necessary components.

5. **Indirect Modification**: When working with the Config Repository, we needed to use the `set()` method rather than array access to modify configuration values.

These issues were addressed in our test implementation, but they are important to keep in mind when working with the Laravel integration or adding new tests in the future.

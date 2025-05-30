# Testing Documentation for timeseries-php

This document provides an overview of the testing approach for the timeseries-php library.

## Test Structure

The tests are organized to mirror the structure of the source code:

```
tests/
├── Core/
│   ├── data/
│   │   ├── MockConfig.php
│   │   ├── MockDriver.php
│   │   └── TestDriver.php
│   ├── DataPointTest.php
│   ├── QueryResultTest.php
│   ├── QueryTest.php
│   ├── SimpleTest.php
│   └── TSDBFactoryTest.php
├── Drivers/
│   ├── Graphite/
│   │   ├── GraphiteConfigTest.php
│   │   ├── GraphiteDriverTest.php
│   │   └── GraphiteQueryBuilderTest.php
│   ├── InfluxDB/
│   │   ├── InfluxDBDriverTest.php
│   │   └── InfluxDBQueryBuilderTest.php
│   ├── Prometheus/
│   │   ├── PrometheusDriverTest.php
│   │   └── PrometheusQueryBuilderTest.php
│   └── RRDtool/
│       ├── data/
│       ├── FileNameStrategyTest.php
│       ├── FolderStrategyTest.php
│       ├── NoTagsStrategyTest.php
│       ├── RRDtoolDriverTest.php
│       ├── RRDtoolIntegrationTest.php
│       ├── RRDtoolQueryBuilderTest.php
│       ├── RRDtoolXmlIntegrationTest.php
│       └── TagSearchTest.php
├── Support/
│   ├── Cache/
│   │   ├── AbstractCacheTest.php
│   │   ├── CacheFactoryTest.php
│   │   ├── FileCacheTest.php
│   │   └── MemoryCacheTest.php
│   ├── Config/
│   │   ├── data/
│   │   ├── AbstractConfigTest.php
│   │   ├── CacheConfigTest.php
│   │   └── ConfigTestCase.php
│   ├── Logs/
│   │   └── LoggerTest.php
│   ├── Query/
│   └── TSDBFactoryInstanceTest.php
└── Utils/
    ├── ConvertTests.php
    └── RetryableOperationTest.php
```

## Test Coverage

The test suite covers the following components:

### Core Components
- **Query**: Tests for query building, including all query methods (select, where, timeRange, etc.)
- **DataPoint**: Tests for creating and manipulating data points
- **QueryResult**: Tests for handling query results
- **TSDBFactory**: Tests for driver registration and creation
- **SimpleTest**: Basic tests for the library

### Drivers
- **Graphite**: Tests for the Graphite driver implementation, configuration, and query builder
- **InfluxDB**: Tests for the InfluxDB driver implementation and query builder
- **Prometheus**: Tests for the Prometheus driver implementation and query builder
- **RRDtool**: Tests for the RRDtool driver implementation, query builder, tag strategies, and integration tests

### Support
- **Cache**: Tests for the caching system
- **Config**: Tests for the configuration system
- **Logs**: Tests for the logging system
- **Query**: Directory for query-related tests
- **TSDBFactoryInstance**: Tests for the TSDBFactory instance

### Utils
- **Convert**: Tests for the Convert utility class
- **RetryableOperation**: Tests for the RetryableOperation utility class

## Running Tests

To run the tests:

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

### Integration and Benchmark Tests

For running integration and benchmark tests, you need to set up the time series databases first. See [TSDB_SETUP.md](TSDB_SETUP.md) for detailed instructions on how to set up each database for testing.

#### Running Integration Tests with Docker Compose

The easiest way to run integration tests is to use the provided script that automatically starts Docker Compose, runs the tests, and then stops Docker Compose:

```bash
# Run all integration tests with Docker Compose
./docker/run-integration-tests.sh
```

This script will:
1. Start the Docker Compose services (InfluxDB, Prometheus, Graphite, rrdcached)
2. Wait for the services to be ready
3. Run the integration tests
4. Stop the Docker Compose services

#### Running Tests Manually

If you prefer to run the tests manually:

```bash
# Run all integration tests
./vendor/bin/phpunit --group integration

# Run all benchmark tests
./vendor/bin/phpunit --group benchmark
```

## Test Approach

The tests use the following approaches:

1. **Unit Tests**: Testing individual components in isolation
2. **Mock Objects**: Using PHPUnit's mocking capabilities to test components that depend on external services
3. **Reflection**: Using PHP's reflection API to test private/protected properties and methods
4. **Test Inheritance**: Using abstract test cases to avoid code duplication for similar components

## Future Test Improvements

Potential improvements to the test suite:

1. Add integration tests that test the interaction between components
2. Add functional tests that test the library against actual database instances
3. Add performance tests to ensure the library performs well under load
4. Increase test coverage for edge cases and error conditions

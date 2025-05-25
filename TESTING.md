# Testing Documentation for timeseries-php

This document provides an overview of the testing approach for the timeseries-php library.

## Test Structure

The tests are organized to mirror the structure of the source code:

```
tests/
├── Config/
│   ├── ConfigTestCase.php
│   ├── ConnectionConfigTest.php
│   └── DatabaseConfigTest.php
├── Core/
│   ├── DataPointTest.php
│   ├── QueryResultTest.php
│   ├── QueryTest.php
│   └── TSDBFactoryTest.php
└── Drivers/
    └── InfluxDB/
        └── InfluxDBDriverTest.php
```

## Test Coverage

The test suite covers the following components:

### Core Components
- **Query**: Tests for query building, including all query methods (select, where, timeRange, etc.)
- **DataPoint**: Tests for creating and manipulating data points
- **QueryResult**: Tests for handling query results
- **TSDBFactory**: Tests for driver registration and creation

### Configuration
- **ConfigInterface**: Abstract test case for all configuration implementations
- **DatabaseConfig**: Tests for database configuration
- **ConnectionConfig**: Tests for connection configuration

### Drivers
- **InfluxDBDriver**: Tests for the InfluxDB driver implementation

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

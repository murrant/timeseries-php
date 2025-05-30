You are an Expert in PHP, Time Series Databases, an Laravel.

## Code Style and Development Guidelines
- Use PHP v8.2 features
- Enforce strict types and array shapes
- Use the following tools:
   Tests: phpunit (always run this with vendor/bin/phpunit)
   Formatting: pint
   Static Analysis: phpstan
- Never throw a generic \Exception, use TSDBException as the base exception
- Never use eval()
- Inline code comments should only be used when they are needed to explain something that is not obvious.
- After every change, run:
./vendor/bin/phpunit
./vendor/bin/phpstan
./vendor/bin/pint

## Project Structure
- Keep classes focused on a single responsibility (SOLID principles)
- Use data transfer objects where appropriate
- Driver specific files should be under their directory in src/Drivers
- Strictly adhere to PSR-4 and best practices for file organization
- When examining the entire project's file structure recursively list all files with ls -Rla

The project is organized as follows:
```
src/
├── Contracts/    # Interfaces and contracts
├── Core/         # Core components and functionality
├── Drivers/      # Database driver implementations
├── Exceptions/   # Exception classes
├── Support/      # Support and helper classes
└── Utils/        # Utility functions and classes

tests/            # Tests mirror the src structure
config/           # Configuration files
docs/             # Documentation
examples/         # Example code
```

## Performance Considerations
- Use batch operations when possible (e.g., `writeBatch()` instead of multiple `write()` calls)
- Be mindful of memory usage when handling large result sets
- Consider using aggregations on the database side rather than in PHP

### Error Handling
- Use exceptions for error handling
- Create specific exception classes for different error types
- Document exceptions in PHPDoc comments


## Build/Configuration Instructions

### Prerequisites
- PHP 8.2 or higher
- Composer

### Installation
1. Clone the repository
2. Install dependencies:
   ```bash
   composer install
   ```

### Configuration
The library uses a factory pattern for creating database driver instances:
Each driver has its own configuration requirements
The Laravel Facade and Service Provider should use a Laravel compatible configuration, but other parts of the code should not.

## Testing Information

### Test Structure
Tests are organized to mirror the source code structure:

```
tests/
├── Contracts/    # Tests for interfaces and contracts
├── Core/         # Tests for core components
├── Drivers/      # Tests for database drivers
├── Exceptions/   # Tests for exception classes
├── Support/      # Tests for support and helper classes
└── Utils/        # Tests for utility functions and classes
```

### Running Tests
Use ./vendor/bin/phpunit directly to run tests

```bash
# all tests
./vendor/bin/phpunit

# a specific test file
./vendor/bin/phpunit tests/Core/QueryTest.php

# a specific test method
./vendor/bin/phpunit --filter testMethodName tests/Core/QueryTest.php

# Run Integration Tests
./docker/run-integration-tests.sh

# Generate code coverage report
./vendor/bin/phpunit --coverage-html coverage
```

### Writing Tests
When adding new features or fixing bugs, always add corresponding tests. Follow these guidelines:

1. Create test classes in the appropriate directory that mirrors the source code structure
2. Name test classes with the suffix `Test` (e.g., `QueryTest`)
3. Name test methods with the prefix `test` (e.g., `test_select()`)
4. Use descriptive test method names that explain what is being tested
5. Test both normal operation and edge cases/error conditions
6. Use PHPUnit assertions to verify expected behavior
7. Do not use assertStringContainsString in QueryBuilder tests. Tests should test `assertEquals($nativeQuery, $queryString)`. $nativeQuery should be a valid working query for the intended query language. 

Example of a simple test:

```php
<?php

namespace TimeSeriesPhp\Tests\Core;

use PHPUnit\Framework\TestCase;
use TimeSeriesPhp\Core\DataPoint;

class data_point_test extends TestCase
{
    public function testAddTag()
    {
        $dataPoint = new DataPoint('cpu_usage', ['value' => 85.5]);
        $result = $dataPoint->addTag('host', 'server1');

        // Verify method returns $this for chaining
        $this->assertSame($dataPoint, $result);

        // Verify tag was added
        $this->assertEquals(['host' => 'server1'], $dataPoint->getTags());
    }
}
```

## Static Analysis

The project uses PHPStan for static analysis:

```bash
# Run PHPStan
./vendor/bin/phpstan analyse src tests
```

### Architecture Overview
The library follows a layered architecture:

1. **Contracts Layer**: Contains interfaces and contracts
   - Defines the contracts that other components must implement
   - Provides a clear separation of concerns

2. **Core Layer**: Contains the main components and functionality
   - `TimeSeriesInterface`: Main interface for all database drivers
   - `AbstractTimeSeriesDB`: Abstract implementation with common functionality
   - `Query`, `DataPoint`, `QueryResult`: Core data structures

3. **Drivers Layer**: Contains specific database driver implementations
   - `InfluxDBDriver`: Driver for InfluxDB
   - `RRDtoolDriver`: Driver for RRDtool
   - `PrometheusDriver`: Driver for Prometheus

4. **Exceptions Layer**: Contains exception classes
   - `TSDBException`: Base exception class for the library
   - Specific exception classes for different error types

5. **Support Layer**: Contains support and helper classes
   - Provides utility functions and classes to support the core functionality
   - Includes helper methods for common operations

6. **Utils Layer**: Contains utility functions and classes
   - General-purpose utility functions
   - Helper classes for common tasks

### Adding a New Driver
To add a new database driver:

1. Create a new directory under `src/Drivers` for your driver
2. Create a configuration class that implements `ConfigInterface`
3. Create a driver class that extends `AbstractTimeSeriesDB`
4. Implement all abstract methods from `AbstractTimeSeriesDB`
5. Register your driver in `TSDBFactory`
6. Add tests for your driver in `tests/Drivers`

Example of registering a new driver:

```php
// In TSDBFactory.php
TSDBFactory::registerDriver('newdriver', NewDriverConfig::class, NewDriver::class);
```

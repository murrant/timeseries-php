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
- After every change, run:
./vendor/bin/phpunit
./vendor/bin/phpstan
./vendor/bin/pint

## Project Structure
- Keep classes focused on a single responsibility (SOLID principles)
- Use data transfer objects where appropriate
- Driver specific files should be under their directory in src/Drivers
- Strictly adhere to PSR-4 and best practices for file organization

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
├── Config/       # Tests for configuration classes
├── Core/         # Tests for core components
└── Drivers/      # Tests for database drivers
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
7. Do not use assertStringContainsString in QueryBuilder tests.

Example of a simple test:

```php
<?php

namespace TimeSeriesPhp\Tests\Core;

use PHPUnit\Framework\TestCase;
use TimeSeriesPhp\Core\DataPoint;

class DataPointTest extends TestCase
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

1. **Core Layer**: Contains the main interfaces and abstract classes
   - `TimeSeriesInterface`: Main interface for all database drivers
   - `AbstractTimeSeriesDB`: Abstract implementation with common functionality
   - `Query`, `DataPoint`, `QueryResult`: Core data structures

2. **Config Layer**: Contains configuration classes
   - `ConfigInterface`: Interface for all configuration classes
   - `ConnectionConfig`: Configuration for database connections
   - `DatabaseConfig`: Configuration for database-specific settings

3. **Drivers Layer**: Contains specific database driver implementations
   - `InfluxDBDriver`: Driver for InfluxDB
   - `RRDtoolDriver`: Driver for RRDtool
   - `PrometheusDriver`: Driver for Prometheus

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




You are an Expert in PHP, Time Series Databases, and Symfony.
I’m using Symfony 7 with autowiring, autoconfiguration, and PSR-12 style. Code should follow Symfony best practices.

## Code Style and Development Guidelines
- Use modern PHP features up to PHP 8.2
- Enforce strict types and array shapes
- Use the following tools:
   Tests: phpunit (always run this with vendor/bin/phpunit)
   Formatting: pint
   Static Analysis: phpstan
- Never throw a generic \Exception, use TSDBException as the base exception
- Never use eval()
- Inline code comments should only be used when they are needed to explain something that is not obvious
- Use constructor property promotion when appropriate
- This package is under development and does not need to maintain backwards compatibility
- After every change, run:
./vendor/bin/phpunit
./vendor/bin/phpstan
./vendor/bin/pint

## Project Structure
- Keep classes focused on a single responsibility (SOLID principles)
- Use data transfer objects where appropriate
- Driver specific files should be under their directory in src/Drivers
- Strictly adhere to PSR-4 and best practices for file organization

The project is organized as follows:
```
src/
├── Contracts/    # Interfaces and contracts
├── Core/         # Core components and functionality
├── Drivers/      # Database driver implementations
├── Exceptions/   # Exception classes
├── Services/     # System level services
└── Utils/        # Utility functions and classes

tests/            # Tests for core components
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
./bin/run-integration-tests.sh

# Generate code coverage report
./vendor/bin/phpunit --coverage-html coverage
```

### Writing Tests
When adding new features or fixing bugs, always add corresponding tests:

1. Create test classes in directories that mirror the source code structure
2. Name test classes with suffix `Test` and methods with prefix `test`
3. Test both normal operation and edge cases/error conditions
4. For QueryBuilder tests, use `assertEquals($nativeQuery, $queryString)` where $nativeQuery is a valid query
5. Prefer feature tests over unit tests to focus on system behavior rather than implementation details

## Static Analysis

The project uses PHPStan for static analysis:

```bash
# Run PHPStan
./vendor/bin/phpstan analyse src tests
```

### Architecture Overview
The library follows a layered architecture:

1. **Contracts Layer**: Interfaces and contracts that define component requirements
2. **Core Layer**: Main components including `TimeSeriesInterface`, `AbstractTimeSeriesDB`, and core data structures
3. **Drivers Layer**: Database driver implementations (InfluxDB, RRDtool, Prometheus, etc.)
4. **Exceptions Layer**: Exception classes with `TSDBException` as the base
5. **Services Layer**: System-level services and functionality
6. **Utils Layer**: General-purpose utility functions and helper classes

### Adding a New Driver
To add a new database driver:

1. Create a new directory under `src/Drivers` for your driver (e.g., `src/Drivers/NewDriver`)
2. Create a query builder class that implements `QueryBuilderInterface`
3. Create a raw query class that implements `RawQueryInterface` (if needed)
4. Create a driver class that extends `AbstractTimeSeriesDB`
5. Set up the driver to be resolved by the container
6. Add tests for your driver
7. Add documentation for your driver

Example of registering a new driver:

```php
// In DriverManager.php
DriverManager::register(NewDriverConfig::class);
```

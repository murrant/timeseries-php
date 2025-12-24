You are an Expert in PHP, Time Series Databases, and Symfony.
I’m using Symfony 7 with autowiring, autoconfiguration, and PSR-12 style. Code should follow Symfony best practices.

## Code Style and Development Guidelines
- Use modern PHP features up to PHP 8.3
- Enforce strict types and array shapes
- Use the following tools:
   Tests: pest
   Formatting: pint
   Static Analysis: phpstan
- Never throw a generic \Exception, use TSDBException as the base exception
- Never use eval() or match(true)
- Inline code comments should only be used when they are needed to explain something that is not obvious
- Use constructor property promotion when appropriate
- This package is under development and does not need to maintain backwards compatibility
- After every change, run:
composer test

## Project Structure
- Keep classes focused on a single responsibility (SOLID principles)
- Use data transfer objects where appropriate
- Driver specific files should be under their directory in packages/<name>-driver
- Strictly adhere to PSR-4 and best practices for file organization

The project is a monorepo and is organized as follows:
```
packages/
├── core/    # Core data objects and contracts
├── <name>-driver/    # Database driver implementation
│   ├── src/          # Source code for the driver
│   └── tests/        # Tests for the driver
├── <name>-bridge/    # Adapters for integrating with frameworks
│   ├── src/          # Source code for the framework 
│   └── tests/        # Tests for the framework
docs/             # Documentation
```

### Error Handling
- Use exceptions for error handling
- Create specific exception classes for different error types
- All exceptions should extend TimeseriesPhp\Core\Exceptions\TimeseriesException
- Document exceptions in PHPDoc comments

## Build/Configuration Instructions

### Prerequisites
- PHP 8.3 or higher
- Composer

### Installation
1. Clone the repository
2. Install dependencies:
   ```bash
   composer install
   ```

## Testing Information

### Running Tests
Use ./vendor/bin/phpunit directly to run tests

```bash
# all tests
composer test

# Generate code coverage report
composer test:type-coverage
```

### Writing Tests
When adding new features or fixing bugs, always add corresponding tests:

1. Create pest test files in directories that mirror the source code structure in the package/<package name>/test directory
2. Name test files with suffix `Test`
3. Test both normal operation and edge cases/error conditions
4. Prefer feature tests over unit tests to focus on system behavior rather than implementation details

## Static Analysis

The project uses PHPStan for static analysis:

```bash
# Run PHPStan
composer test:types
```

### Architecture Overview
See: docs/Overview.md

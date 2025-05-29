# TimeSeriesPhp Reorganization Plan

## Current Structure Analysis

### Overview
TimeSeriesPhp is a PHP library that provides a unified interface for interacting with various time series databases (InfluxDB, Prometheus, Graphite, RRDtool). The library follows a well-structured architecture with clear separation of concerns:

- **Contracts**: Interfaces defining the API
- **Core**: Core implementations and factory classes
- **Drivers**: Database-specific implementations
- **Exceptions**: Custom exceptions
- **Support**: Support classes including Laravel integration
- **Utils**: Utility classes

### Strengths
1. **Well-defined interfaces**: The library has clear interfaces in the Contracts namespace that define the API.
2. **Fluent query builder**: The Query class provides a comprehensive and intuitive fluent interface for building queries.
3. **Factory pattern**: The TSDBFactory provides a clean entry point for creating database instances.
4. **Driver abstraction**: The library successfully abstracts the differences between various time series databases.
5. **Comprehensive API documentation**: The API.md file provides detailed documentation of the library's features.

### Areas for Improvement
1. **Limited examples**: Only one example (InfluxDB) is provided, making it harder for users to understand how to use other drivers.
2. **Documentation organization**: While comprehensive, the documentation could be better organized for new users.
3. **Entry points**: The relationship between direct usage and Laravel integration could be clearer.
4. **Discoverability**: Some advanced features might not be immediately obvious to new users.
5. **Consistency**: Some naming and organization patterns could be more consistent.

## Reorganization Recommendations

### 1. Documentation Improvements

#### 1.1 Create a Getting Started Guide
Create a new `docs/getting-started.md` file that provides:
- A quick introduction to the library
- Installation instructions
- Basic usage examples for all supported drivers
- Common patterns and best practices

#### 1.2 Reorganize API Documentation
Split the current API.md into multiple focused documents:
- `docs/api/overview.md`: Introduction and basic concepts
- `docs/api/factory.md`: Factory usage and configuration
- `docs/api/data-points.md`: Working with data points
- `docs/api/querying.md`: Building and executing queries
- `docs/api/database-management.md`: Database management operations
- `docs/api/error-handling.md`: Error handling strategies
- `docs/api/drivers/`: Directory with driver-specific documentation

#### 1.3 Create a Cookbook
Add a `docs/cookbook.md` with recipes for common tasks:
- Setting up time-based aggregations
- Implementing caching strategies
- Handling high-volume writes
- Optimizing queries for performance
- Migrating between different database backends

### 2. Examples Expansion

#### 2.1 Add Examples for All Drivers
Create example files for all supported drivers:
- `examples/prometheus_example.php`
- `examples/graphite_example.php`
- `examples/rrdtool_example.php`

#### 2.2 Create Task-Based Examples
Add examples that demonstrate common tasks:
- `examples/batch_writing.php`: Efficient batch writing
- `examples/aggregation_queries.php`: Complex aggregation queries
- `examples/error_handling.php`: Proper error handling
- `examples/laravel_integration.php`: Using with Laravel

### 3. Code Organization Improvements

#### 3.1 Standardize Driver Structure
Ensure all drivers follow the same structure:
- `Drivers/{Driver}/`: Main driver directory
  - `{Driver}Driver.php`: Main driver implementation
  - `{Driver}Config.php`: Driver configuration
  - `Query/`: Query-related classes
    - `{Driver}QueryBuilder.php`: Driver-specific query builder
    - `{Driver}RawQuery.php`: Raw query implementation
  - `Exception/`: Driver-specific exceptions

#### 3.2 Improve Factory Discoverability
- Add more comprehensive PHPDoc comments to the factory methods
- Consider adding a `DriverManager` class that provides a more intuitive API for managing drivers

#### 3.3 Enhance Laravel Integration
- Improve the Laravel service provider documentation
- Add more configuration options in the Laravel config file
- Create Laravel-specific examples and documentation

### 4. API Enhancements

#### 4.1 Simplified Entry Points
Consider adding simplified entry points for common operations:
```php
// Current approach
$db = TSDBFactory::create('influxdb', $config);
$dataPoint = new DataPoint('cpu_usage', ['value' => 85.5], ['host' => 'server1']);
$db->write($dataPoint);

// Potential simplified approach
$ts = new TimeSeries('influxdb', $config);
$ts->write('cpu_usage', ['value' => 85.5], ['host' => 'server1']);
```

#### 4.2 Consistent Method Naming
Review method names across the codebase for consistency, especially in the Query builder.

#### 4.3 Add Helper Methods
Add helper methods for common operations to reduce boilerplate:
```php
// Helper for creating and writing a data point in one call
$db->writePoint('cpu_usage', ['value' => 85.5], ['host' => 'server1']);

// Helper for simple queries
$result = $db->queryLast('cpu_usage', 'value', ['host' => 'server1']);
```

### 5. Testing and Quality Assurance

#### 5.1 Expand Test Coverage
- Ensure all drivers have comprehensive test coverage
- Add integration tests for all supported databases
- Add performance benchmarks

#### 5.2 Add Code Quality Tools
- Add PHPStan configuration for static analysis
- Add GitHub Actions for continuous integration

## Implementation Priority

1. **Documentation Improvements**: Start with better documentation to make the current API more approachable.
2. **Examples Expansion**: Add examples for all drivers to help users get started.
3. **Code Organization Improvements**: Standardize the driver structure for consistency.
4. **API Enhancements**: Add helper methods and simplified entry points.
5. **Testing and Quality Assurance**: Expand test coverage and add code quality tools.

## Conclusion

The TimeSeriesPhp library has a solid foundation with a well-designed API and good separation of concerns. The proposed reorganization focuses on making the API more obvious and approachable through improved documentation, expanded examples, and some targeted API enhancements. These changes should make it easier for new users to get started with the library while maintaining the flexibility and power of the current design.

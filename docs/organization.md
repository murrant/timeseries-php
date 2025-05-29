# File Organization Plan for TimeSeriesPhp

## Current Structure Assessment

The current file structure of the TimeSeriesPhp project follows a layered architecture with the following main directories:

### Source Code (`src/`)

1. **Core/** - Contains core interfaces and classes:
   - `TimeSeriesInterface.php` - Main interface for all database drivers
   - `DataPoint.php` - Represents a data point in a time series
   - `Query.php` - Represents a query to a time series database
   - `QueryResult.php` - Represents the result of a query
   - `TSDBFactory.php` - Static facade for creating database driver instances

2. **Support/** - Contains supporting classes:
   - `AbstractTimeSeriesDB.php` - Abstract implementation with common functionality
   - `TSDBFactoryInstance.php` - Non-static implementation of the factory pattern
   - **Config/** - Configuration-related classes
   - **Cache/** - Caching-related classes
   - **Logs/** - Logging-related classes
   - **Query/** - Query-related classes

3. **Drivers/** - Contains specific database driver implementations:
   - **InfluxDB/** - Driver for InfluxDB
   - **Prometheus/** - Driver for Prometheus
   - **RRDtool/** - Driver for RRDtool
   - **Graphite/** - Driver for Graphite

4. **Exceptions/** - Contains exception classes:
   - `TSDBException.php` - Base exception class
   - Various specific exception classes

5. **Utils/** - Contains utility classes:
   - `Convert.php` - Conversion utilities
   - `File.php` - File-related utilities
   - `RetryableOperation.php` - Retry logic for operations
   - `TimeHelper.php` - Time-related utilities
   - `ValidationHelper.php` - Validation utilities

### Tests (`tests/`)

The tests directory mirrors the source code structure:

1. **Core/** - Tests for core components
2. **Support/** - Tests for supporting classes
3. **Drivers/** - Tests for database drivers
4. **Utils/** - Tests for utility classes

## Organizational Issues and Areas for Improvement

While the current structure is generally well-organized, there are some areas that could be improved:

1. **Inconsistent Placement of Core Components**:
   - `TSDBFactory.php` is in the Core directory, but `TSDBFactoryInstance.php` is in the Support directory
   - `AbstractTimeSeriesDB.php` is in the Support directory, but it's a core component of the architecture

2. **Lack of Clear Separation Between Interfaces and Implementations**:
   - Interfaces and their implementations are spread across different directories

3. **Potential for Better Organization of Driver-Specific Components**:
   - Each driver has its own configuration classes, but they're all in the same directory

4. **Lack of Clear Documentation on File Organization**:
   - There's no clear documentation on where new files should be placed

## Recommended Structure

Here's a recommended structure that addresses these issues:

```
src/
├── Contracts/                  # All interfaces
│   ├── Config/                 # Configuration interfaces
│   ├── Driver/                 # Driver interfaces
│   ├── Query/                  # Query interfaces
│   └── Cache/                  # Cache interfaces
├── Core/                       # Core implementations
│   ├── Config/                 # Core configuration implementations
│   ├── Driver/                 # Core driver implementations
│   ├── Query/                  # Core query implementations
│   ├── Cache/                  # Core cache implementations
│   ├── Factory/                # Factory implementations
│   └── Data/                   # Data structures (DataPoint, QueryResult)
├── Drivers/                    # Database driver implementations
│   ├── InfluxDB/               # InfluxDB driver
│   │   ├── Config/             # InfluxDB configuration
│   │   ├── Query/              # InfluxDB query builder
│   │   └── Driver.php          # InfluxDB driver implementation
│   ├── Prometheus/             # Prometheus driver
│   │   ├── Config/             # Prometheus configuration
│   │   ├── Query/              # Prometheus query builder
│   │   └── Driver.php          # Prometheus driver implementation
│   ├── RRDtool/                # RRDtool driver
│   │   ├── Config/             # RRDtool configuration
│   │   ├── Query/              # RRDtool query builder
│   │   └── Driver.php          # RRDtool driver implementation
│   └── Graphite/               # Graphite driver
│       ├── Config/             # Graphite configuration
│       ├── Query/              # Graphite query builder
│       └── Driver.php          # Graphite driver implementation
├── Exceptions/                 # Exception classes
│   ├── Config/                 # Configuration exceptions
│   ├── Driver/                 # Driver exceptions
│   ├── Query/                  # Query exceptions
│   └── Cache/                  # Cache exceptions
├── Support/                    # Supporting classes
│   ├── Logs/                   # Logging classes
│   └── Laravel/                # Laravel integration
└── Utils/                      # Utility classes
    ├── Time/                   # Time-related utilities
    ├── File/                   # File-related utilities
    └── Validation/             # Validation utilities
```

### Tests Structure

The tests directory should mirror the source code structure:

```
tests/
├── Contracts/                  # Tests for interfaces
├── Core/                       # Tests for core implementations
├── Drivers/                    # Tests for database drivers
│   ├── InfluxDB/               # Tests for InfluxDB driver
│   ├── Prometheus/             # Tests for Prometheus driver
│   ├── RRDtool/                # Tests for RRDtool driver
│   └── Graphite/               # Tests for Graphite driver
├── Exceptions/                 # Tests for exception classes
├── Support/                    # Tests for supporting classes
└── Utils/                      # Tests for utility classes
```

## Justification for Recommended Structure

1. **Clear Separation of Interfaces and Implementations**:
   - All interfaces are in the `Contracts` directory, making it clear what the public API is
   - Implementations are in their respective directories

2. **Logical Grouping of Related Components**:
   - Each driver has its own directory with subdirectories for configuration, query builders, etc.
   - Core components are grouped together in the `Core` directory

3. **Consistent Naming and Organization**:
   - All directories and files follow a consistent naming convention
   - Related components are grouped together

4. **Better Support for Future Extensions**:
   - The structure makes it easy to add new drivers, query builders, etc.
   - The separation of interfaces and implementations makes it easier to extend the library

## Migration Steps

To migrate from the current structure to the recommended structure, follow these steps:

1. **Create the new directory structure**:
   - Create the new directories as outlined above

2. **Move interfaces to the Contracts directory**:
   - Move `TimeSeriesInterface.php` to `Contracts/Driver/TimeSeriesInterface.php`
   - Move query interfaces to `Contracts/Query/`
   - Move configuration interfaces to `Contracts/Config/`

3. **Move core implementations to the Core directory**:
   - Move `AbstractTimeSeriesDB.php` to `Core/Driver/AbstractTimeSeriesDB.php`
   - Move `TSDBFactory.php` to `Core/Factory/TSDBFactory.php`
   - Move `TSDBFactoryInstance.php` to `Core/Factory/TSDBFactoryInstance.php`
   - Move `DataPoint.php` and `QueryResult.php` to `Core/Data/`

4. **Reorganize driver implementations**:
   - For each driver, create subdirectories for configuration, query builders, etc.
   - Move driver-specific files to their respective directories

5. **Update namespace declarations and imports**:
   - Update namespace declarations in all moved files
   - Update import statements in all files that reference moved files

6. **Update tests to mirror the new structure**:
   - Reorganize test files to mirror the new source code structure
   - Update namespace declarations and imports in test files

7. **Update documentation**:
   - Update documentation to reflect the new structure
   - Add guidelines for where new files should be placed

## Best Practices for Future Development

1. **Follow PSR-4 Autoloading Standard**:
   - Namespace and directory structure should match
   - One class per file, with the filename matching the class name

2. **Keep Classes Focused on a Single Responsibility**:
   - Follow the Single Responsibility Principle
   - Create new classes rather than adding unrelated functionality to existing ones

3. **Use Interfaces for Public API**:
   - Define interfaces for all public API components
   - Implement interfaces in concrete classes

4. **Group Related Components Together**:
   - Keep related files in the same directory
   - Use subdirectories to organize complex components

5. **Follow Consistent Naming Conventions**:
   - Use consistent naming for files, classes, methods, etc.
   - Use suffixes to indicate the role of a class (e.g., `Interface`, `Exception`)

6. **Document File Organization**:
   - Document where new files should be placed
   - Provide examples of how to add new components

7. **Keep Driver-Specific Code in Driver Directories**:
   - All code specific to a particular driver should be in that driver's directory
   - Use subdirectories to organize complex drivers

8. **Use Data Transfer Objects Where Appropriate**:
   - Use DTOs to transfer data between layers
   - Keep DTOs simple and focused on data, not behavior

9. **Create Specific Exception Classes**:
   - Create specific exception classes for different error types
   - Group related exceptions in subdirectories

10. **Test All Components**:
    - Create tests for all components
    - Organize tests to mirror the source code structure

# TimeSeriesPhp API Overview

This document provides an introduction to the TimeSeriesPhp API and its basic concepts.

## Introduction

TimeSeriesPhp is a PHP library for working with time series databases. It provides a unified interface for interacting with various time series database systems, including InfluxDB, Prometheus, Graphite, and RRDtool.

The library is designed with the following goals in mind:

- **Unified API**: Write code once that works with multiple time series database backends
- **Fluent Interface**: Intuitive, chainable methods for building queries
- **Type Safety**: Strong typing and clear interfaces
- **Extensibility**: Easy to add support for new database systems
- **Error Handling**: Comprehensive exception hierarchy for robust error handling

## Core Concepts

### Time Series Databases

A time series database (TSDB) is a database optimized for time-stamped or time series data. Time series data are simply measurements or events that are tracked, monitored, downsampled, and aggregated over time.

Examples of time series data include:

- Server metrics (CPU load, memory usage)
- Application performance metrics
- Network data
- Sensor data
- Stock trading prices

### Measurements

In TimeSeriesPhp, a measurement (sometimes called a metric) is a collection of data points that share the same name and typically represent the same type of data. For example, `cpu_usage` might be a measurement that contains data points about CPU usage across different servers.

### Data Points

A data point represents a single measurement at a specific point in time. It consists of:

- **Measurement Name**: The name of the measurement (e.g., `cpu_usage`)
- **Fields**: The actual values being measured (e.g., `value: 85.5`)
- **Tags**: Metadata used to identify the data point (e.g., `host: server1`)
- **Timestamp**: When the measurement was taken

### Queries

Queries allow you to retrieve and analyze data from a time series database. TimeSeriesPhp provides a fluent query builder that abstracts the differences between query languages of different database systems.

## Architecture

TimeSeriesPhp follows a layered architecture:

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
   - `PrometheusDriver`: Driver for Prometheus
   - `GraphiteDriver`: Driver for Graphite
   - `RRDtoolDriver`: Driver for RRDtool

4. **Support Layer**: Contains support classes
   - Laravel integration
   - Utility classes

## Basic Workflow

The typical workflow for using TimeSeriesPhp is:

1. **Create a database instance** using the `TSDBFactory`
2. **Write data** using the `write()` or `writeBatch()` methods
3. **Query data** using the `query()` method with a `Query` object
4. **Process results** from the query
5. **Close the connection** when done

For more detailed information on specific aspects of the API, refer to the following documents:

- [Factory](factory.md): Creating and managing database instances
- [Data Points](data-points.md): Working with data points
- [Querying](querying.md): Building and executing queries
- [Database Management](database-management.md): Managing databases and measurements
- [Error Handling](error-handling.md): Handling errors and exceptions
- [Drivers](drivers/): Driver-specific documentation

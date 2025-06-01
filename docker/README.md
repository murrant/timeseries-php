# Docker Environment for TimeSeriesPhp

This directory contains Docker configuration files and utilities for running and testing the TimeSeriesPhp library with various time series databases.

## Docker Compose

The `docker-compose.yml` file defines the following services:

- **InfluxDB**: A time series database optimized for high-write-throughput, storage, and querying of time series data.
- **Prometheus**: A monitoring system and time series database with a dimensional data model, flexible query language, and alerting capabilities.
- **Graphite**: A monitoring tool that stores numeric time-series data and renders graphs of this data on demand.
- **RRDcached**: A daemon that receives updates to RRD files, accumulates them, and, if enough have been accumulated or a defined time has passed, writes the updates to the RRD files.

## Sample Data Generator

The `generate_sample_data.php` script is provided to generate sample data for all the time series databases defined in the docker-compose.yml file.

### Features

- Connects to all configured time series databases
- Generates realistic sample data with variance
- Writes data at regular intervals
- Configurable measurements, fields, and tags
- Error handling and logging

### Usage

1. Make sure all the database services are running:

```bash
docker-compose up -d
```

2. Run the sample data generator:

```bash
docker-compose exec php php /var/www/html/docker/generate_sample_data.php
```

Or, if you're running PHP locally:

```bash
php docker/generate_sample_data.php
```

### Configuration

The script includes configuration for:

- **Database connections**: URLs, credentials, and other connection parameters
- **Measurements**: CPU, memory, network, and disk metrics
- **Fields**: Various metrics with min/max ranges
- **Tags**: Host, datacenter, device, and interface identifiers
- **Interval**: Time between data writes (default: 10 seconds)
- **Iterations**: Number of data points to generate (default: 1000, set to 0 for infinite)

### Customization

You can modify the script to:

- Add or remove databases
- Change connection parameters
- Add new measurements, fields, or tags
- Adjust the data generation ranges
- Change the write interval
- Modify the number of iterations

## Integration Tests

The `run-integration-tests.sh` script is used to run integration tests against the Docker services. It ensures that all services are up and running before executing the tests.

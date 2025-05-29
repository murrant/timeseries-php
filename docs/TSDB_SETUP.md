# Setting Up Time Series Databases for Testing

This document provides instructions on how to set up each time series database (TSDB) for integration and benchmark tests in the timeseries-php library.

## Table of Contents

1. [Using Docker Compose (Recommended)](#using-docker-compose-recommended)
2. [InfluxDB](#influxdb)
3. [Prometheus](#prometheus)
4. [Graphite](#graphite)
5. [RRDtool](#rrdtool)

## Using Docker Compose (Recommended)

The easiest way to set up all the time series databases at once is to use the provided Docker Compose file located in the `docker` directory. This will start InfluxDB, Prometheus, and Graphite with the correct configuration for running the integration and benchmark tests.

> **Note:** RRDtool is a command-line tool, not a server, so it's not included in the Docker Compose setup. You'll need to install RRDtool locally as described in the [RRDtool](#rrdtool) section.

### Prerequisites

- [Docker](https://docs.docker.com/get-docker/)
- [Docker Compose](https://docs.docker.com/compose/install/)

### Starting the Services

```bash
# Start all services in the background
docker-compose -f docker/docker-compose.yml up -d
```

This will start:
- InfluxDB on port 8086
- Prometheus on port 9090
- Graphite on ports 2003 (Carbon) and 8080 (Web interface)

### Stopping the Services

```bash
# Stop all services
docker-compose -f docker/docker-compose.yml down

# Stop all services and remove volumes (this will delete all data)
docker-compose -f docker/docker-compose.yml down -v
```

### Running Tests with Docker Compose

When running tests with the Docker Compose setup, you can use the default environment variables as they match the configuration in the Docker Compose file:

```bash
# Run all integration tests
./vendor/bin/phpunit --group integration

# Run all benchmark tests
./vendor/bin/phpunit --group benchmark
```

If you need to run tests for a specific driver:

```bash
# Run InfluxDB integration tests
./vendor/bin/phpunit tests/Drivers/InfluxDB/InfluxDBIntegrationTest.php

# Run Prometheus integration tests
./vendor/bin/phpunit tests/Drivers/Prometheus/PrometheusIntegrationTest.php

# Run Graphite integration tests
./vendor/bin/phpunit tests/Drivers/Graphite/GraphiteIntegrationTest.php
```

## InfluxDB

### Installation

#### Docker (Recommended)

```bash
docker run -d --name influxdb \
  -p 8086:8086 \
  -e DOCKER_INFLUXDB_INIT_MODE=setup \
  -e DOCKER_INFLUXDB_INIT_USERNAME=admin \
  -e DOCKER_INFLUXDB_INIT_PASSWORD=password \
  -e DOCKER_INFLUXDB_INIT_ORG=my-org \
  -e DOCKER_INFLUXDB_INIT_BUCKET=test_integration \
  -e DOCKER_INFLUXDB_INIT_ADMIN_TOKEN=my-token \
  influxdb:2.7
```

#### Manual Installation

1. Download InfluxDB from the [official website](https://www.influxdata.com/downloads/)
2. Follow the installation instructions for your operating system
3. Start InfluxDB and set up an initial user, organization, and bucket:

```bash
influx setup \
  --username admin \
  --password password \
  --org my-org \
  --bucket test_integration \
  --token my-token \
  --force
```

### Configuration for Tests

The integration and benchmark tests use the following environment variables:

- `INFLUXDB_URL`: The URL of the InfluxDB instance (default: `http://localhost:8086`)
- `INFLUXDB_TOKEN`: The API token for authentication (default: `my-token`)
- `INFLUXDB_ORG`: The organization name (default: `my-org`)
- `INFLUXDB_BUCKET`: The bucket name (default: `test_integration` for integration tests, `benchmark_test` for benchmark tests)

You can set these environment variables before running the tests:

```bash
export INFLUXDB_URL=http://localhost:8086
export INFLUXDB_TOKEN=my-token
export INFLUXDB_ORG=my-org
export INFLUXDB_BUCKET=test_integration
```

### Running Integration Tests

```bash
./vendor/bin/phpunit tests/Drivers/InfluxDB/InfluxDBIntegrationTest.php
```

### Running Benchmark Tests

```bash
export INFLUXDB_BUCKET=benchmark_test
./vendor/bin/phpunit tests/Benchmarks/InfluxDBBenchmark.php
```

## Prometheus

### Installation

#### Docker (Recommended)

```bash
docker run -d --name prometheus \
  -p 9090:9090 \
  prom/prometheus:latest
```

#### Manual Installation

1. Download Prometheus from the [official website](https://prometheus.io/download/)
2. Extract the archive and navigate to the directory
3. Create or modify the `prometheus.yml` configuration file
4. Start Prometheus:

```bash
./prometheus --config.file=prometheus.yml
```

### Configuration for Tests

The integration and benchmark tests use the following environment variables:

- `PROMETHEUS_URL`: The URL of the Prometheus instance (default: `http://localhost:9090`)
- `PROMETHEUS_TIMEOUT`: The timeout in seconds (default: `5` for integration tests, `30` for benchmark tests)

You can set these environment variables before running the tests:

```bash
export PROMETHEUS_URL=http://localhost:9090
export PROMETHEUS_TIMEOUT=5
```

### Running Integration Tests

```bash
./vendor/bin/phpunit tests/Drivers/Prometheus/PrometheusIntegrationTest.php
```

### Running Benchmark Tests

```bash
export PROMETHEUS_TIMEOUT=30
./vendor/bin/phpunit tests/Benchmarks/PrometheusBenchmark.php
```

## Graphite

### Installation

#### Docker (Recommended)

```bash
docker run -d --name graphite \
  -p 2003:2003 \
  -p 8080:8080 \
  graphiteapp/graphite-statsd:latest
```

#### Manual Installation

1. Follow the [official installation guide](https://graphite.readthedocs.io/en/latest/install.html)
2. Ensure the Carbon daemon is running on port 2003 for receiving metrics
3. Ensure the web interface is running on port 8080 for querying metrics

### Configuration for Tests

The integration and benchmark tests use the following environment variables:

- `GRAPHITE_HOST`: The hostname of the Graphite instance (default: `localhost`)
- `GRAPHITE_PORT`: The Carbon port for writing metrics (default: `2003`)
- `GRAPHITE_QUERY_PORT`: The web port for querying metrics (default: `8080`)

You can set these environment variables before running the tests:

```bash
export GRAPHITE_HOST=localhost
export GRAPHITE_PORT=2003
export GRAPHITE_QUERY_PORT=8080
```

### Running Integration Tests

```bash
./vendor/bin/phpunit tests/Drivers/Graphite/GraphiteIntegrationTest.php
```

### Running Benchmark Tests

```bash
./vendor/bin/phpunit tests/Benchmarks/GraphiteBenchmark.php
```

## RRDtool

### Installation

RRDtool is a command-line tool that needs to be installed on the system where the tests will run.

#### Linux (Debian/Ubuntu)

```bash
sudo apt-get update
sudo apt-get install rrdtool
```

#### macOS (Homebrew)

```bash
brew install rrdtool
```

#### Windows

Download the Windows binary from the [official website](https://www.rrdtool.org/) or use a package manager like Chocolatey:

```bash
choco install rrdtool
```

### Configuration for Tests

The RRDtool tests don't use environment variables. Instead, they:

1. Check if the `rrdtool` command is available in the PATH
2. Use a local directory (`tests/Drivers/RRDtool/data/`) for integration tests
3. Use a temporary directory (`sys_get_temp_dir() + '/rrdtool_benchmark/'`) for benchmark tests

Ensure that:
- The `rrdtool` command is available in your PATH
- The test directories are writable by the user running the tests

### Running Integration Tests

```bash
./vendor/bin/phpunit tests/Drivers/RRDtool/RRDtoolIntegrationTest.php
```

### Running Benchmark Tests

```bash
./vendor/bin/phpunit tests/Benchmarks/RRDtoolBenchmark.php
```

## Benchmark Parameters

The benchmark tests use the following default parameters defined in `AbstractDriverBenchmark`:

- `$iterations = 100`: Number of single writes to perform in the single write benchmark
- `$batchSize = 1000`: Number of data points in a batch write benchmark
- `$measurement = 'benchmark_test'`: Name of the measurement to use for benchmarks

These parameters determine the scale of the benchmark tests. You may need to adjust your database configuration to handle this volume of data.

## Running All Tests

To run all integration tests:

```bash
./vendor/bin/phpunit --group integration
```

To run all benchmark tests:

```bash
./vendor/bin/phpunit --group benchmark
```

## Troubleshooting

### InfluxDB

- If you get authentication errors, check that the token is correct
- If you get "bucket not found" errors, ensure the bucket exists or let the test create it

### Prometheus

- Prometheus doesn't support direct writes, so write tests are skipped
- Ensure Prometheus is collecting metrics (at least the `up` metric) for query tests to work

### Graphite

- If connection fails, check that both Carbon (port 2003) and the web interface (port 8080) are running
- Ensure the ports are not blocked by a firewall

### RRDtool

- If the test can't find RRDtool, ensure it's installed and in your PATH
- If you get permission errors, check that the test directories are writable

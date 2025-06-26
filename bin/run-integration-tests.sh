#!/bin/bash

# Script to run integration tests with Docker Compose
# This script will:
# 1. Start Docker Compose services
# 2. Wait for services to be ready
# 3. Run integration tests
# 4. Stop Docker Compose services

set -e

# Function to check if a service is ready
check_service() {
    local service=$1
    local url=$2
    local max_attempts=$3
    local attempt=1

    echo "Waiting for $service to be ready..."
    while [ $attempt -le $max_attempts ]; do
        if curl -s -f "$url" > /dev/null 2>&1; then
            echo "$service is ready!"
            return 0
        fi
        echo "Attempt $attempt/$max_attempts: $service not ready yet, waiting..."
        sleep 5
        attempt=$((attempt + 1))
    done

    echo "ERROR: $service did not become ready in time!"
    return 1
}

# Record start time
START_TIME=$(date +%s)

# Start Docker Compose services
echo "Starting Docker Compose services..."
docker-compose -f "$(dirname "$0")/../docker/docker-compose.yml" up -d

# Wait for services to be ready
check_service "InfluxDB" "http://localhost:8086/health" 12
check_service "Prometheus" "http://localhost:9090/-/healthy" 12
check_service "Graphite" "http://localhost:8080" 12

# Check if rrdcached is ready
echo "Checking if rrdcached is ready..."
if nc -z localhost 42217; then
    echo "rrdcached is ready!"
else
    echo "ERROR: rrdcached is not ready!"
    exit 1
fi

# Run integration tests
echo "Running integration tests..."
./vendor/bin/phpunit --group integration

# Capture the exit code of phpunit
PHPUNIT_EXIT_CODE=$?

# Calculate and display execution time
END_TIME=$(date +%s)
ELAPSED_TIME=$((END_TIME - START_TIME))
echo "Integration tests completed in ${ELAPSED_TIME} seconds."

# Stop Docker Compose services
echo "Stopping Docker Compose services..."
docker-compose -f docker/docker-compose.yml down

# Exit with the phpunit exit code
echo "Exiting with code: $PHPUNIT_EXIT_CODE"
exit $PHPUNIT_EXIT_CODE

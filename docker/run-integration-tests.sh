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

    while [ $attempt -le $max_attempts ]; do
        if curl -s -f "$url" > /dev/null 2>&1; then
            return 0
        fi
        sleep 5
        attempt=$((attempt + 1))
    done

    return 1
}

# Record start time
START_TIME=$(date +%s)

# Start Docker Compose services
docker-compose -f docker/docker-compose.yml up -d > /dev/null 2>&1

# Wait for services to be ready
check_service "InfluxDB" "http://localhost:8086/health" 12 > /dev/null 2>&1
check_service "Prometheus" "http://localhost:9090/-/healthy" 12 > /dev/null 2>&1
check_service "Graphite" "http://localhost:8080" 12 > /dev/null 2>&1

# Check if rrdcached is ready
nc -z localhost 42217 > /dev/null 2>&1

# Run integration tests
./vendor/bin/phpunit --group integration

# Capture the exit code of phpunit
PHPUNIT_EXIT_CODE=$?

# Calculate and display execution time
END_TIME=$(date +%s)
ELAPSED_TIME=$((END_TIME - START_TIME))
echo "Integration tests completed in ${ELAPSED_TIME} seconds."

# Stop Docker Compose services
docker-compose -f docker/docker-compose.yml down > /dev/null 2>&1

# Exit with the phpunit exit code
exit $PHPUNIT_EXIT_CODE

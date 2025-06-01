#!/usr/bin/env php
<?php

namespace TimeSeriesPhp\Docker;

use DateTime;
use TimeSeriesPhp\Core\Data\DataPoint;
use TimeSeriesPhp\TSDB;

/**
 * Script to generate sample data for all time series databases in the docker-compose.yml file.
 *
 * This script will connect to each database and write sample data with some variance
 * to simulate real data. It will run continuously, writing data at regular intervals.
 */

// Set up error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Autoload dependencies
require_once __DIR__.'/../vendor/autoload.php';

// Configuration for each database
$config = [
    'influxdb' => [
        'driver' => 'influxdb',
        'config' => [
            'url' => 'http://influxdb:8086',
            'token' => 'my-token',
            'org' => 'my-org',
            'bucket' => 'example_bucket',
            'timeout' => 30,
            'verify_ssl' => false,
            'debug' => false,
        ],
    ],
    'prometheus' => [
        'driver' => 'prometheus',
        'config' => [
            'url' => 'http://localhost:9090',
            'timeout' => 30,
            'verify_ssl' => false,
            'debug' => false,
        ],
    ],
    'graphite' => [
        'driver' => 'graphite',
        'config' => [
            'host' => 'localhost',
            'port' => 2003,
            'protocol' => 'tcp',
            'timeout' => 30,
            'prefix' => 'sample',
            'batch_size' => 500,
            'web_host' => 'localhost',
            'web_port' => 8080,
            'web_protocol' => 'http',
            'web_path' => '/render',
        ],
    ],
    'rrdtool' => [
        'driver' => 'rrdtool',
        'config' => [
            'rrdtool_path' => 'rrdtool',
            'rrd_dir' => '/tmp/rrd',
            'use_rrdcached' => true,
            'rrdcached_address' => 'localhost:42217',
            'persistent_process' => true,
            'command_timeout' => 180,
            'default_step' => 60, // 1 minute
            'debug' => false,
        ],
    ],
];

// Measurements to generate data for
$measurements = [
    'cpu' => [
        'fields' => [
            'usage' => ['min' => 0, 'max' => 100, 'type' => 'float'],
            'temperature' => ['min' => 30, 'max' => 90, 'type' => 'float'],
            'load' => ['min' => 0, 'max' => 8, 'type' => 'float'],
        ],
        'tags' => [
            'host' => ['server1', 'server2', 'server3'],
            'datacenter' => ['us-east', 'us-west', 'eu-central'],
        ],
    ],
    'memory' => [
        'fields' => [
            'used' => ['min' => 1024, 'max' => 16384, 'type' => 'float'],
            'free' => ['min' => 512, 'max' => 8192, 'type' => 'float'],
            'cached' => ['min' => 256, 'max' => 4096, 'type' => 'float'],
        ],
        'tags' => [
            'host' => ['server1', 'server2', 'server3'],
            'datacenter' => ['us-east', 'us-west', 'eu-central'],
        ],
    ],
    'network' => [
        'fields' => [
            'bytes_in' => ['min' => 0, 'max' => 1000000, 'type' => 'float'],
            'bytes_out' => ['min' => 0, 'max' => 500000, 'type' => 'float'],
            'packets_in' => ['min' => 0, 'max' => 10000, 'type' => 'float'],
            'packets_out' => ['min' => 0, 'max' => 5000, 'type' => 'float'],
        ],
        'tags' => [
            'host' => ['server1', 'server2', 'server3'],
            'interface' => ['eth0', 'eth1', 'wlan0'],
            'datacenter' => ['us-east', 'us-west', 'eu-central'],
        ],
    ],
    'disk' => [
        'fields' => [
            'used' => ['min' => 0, 'max' => 1000, 'type' => 'float'],
            'free' => ['min' => 0, 'max' => 500, 'type' => 'float'],
            'read_ops' => ['min' => 0, 'max' => 1000, 'type' => 'float'],
            'write_ops' => ['min' => 0, 'max' => 500, 'type' => 'float'],
        ],
        'tags' => [
            'host' => ['server1', 'server2', 'server3'],
            'device' => ['sda', 'sdb', 'sdc'],
            'datacenter' => ['us-east', 'us-west', 'eu-central'],
        ],
    ],
];

// Connect to each database
$databases = [];
foreach ($config as $name => $dbConfig) {
    try {
        echo "Connecting to $name...\n";
        $databases[$name] = TSDB::start($dbConfig['driver'], $dbConfig['config']);
        echo "Connected to $name successfully.\n";
    } catch (\Exception $e) {
        echo "Error connecting to $name: ".$e->getMessage()."\n";
    }
}

// Function to generate a random value within a range
function generateValue(float $min, float $max, string $type): float|int
{
    $value = $min + mt_rand() / mt_getrandmax() * ($max - $min);

    return $type === 'int' ? (int) $value : $value;
}

// Function to generate a data point with random values
function generateDataPoint(string $measurement, array $measurementConfig): DataPoint
{
    $fields = [];
    foreach ($measurementConfig['fields'] as $field => $config) {
        $fields[$field] = generateValue($config['min'], $config['max'], $config['type']);
    }

    $tags = [];
    foreach ($measurementConfig['tags'] as $tag => $values) {
        $tags[$tag] = $values[array_rand($values)];
    }

    return new DataPoint($measurement, $fields, $tags);
}

// Main loop to generate and write data
$interval = 10; // seconds between writes
$iterations = 0;
$maxIterations = 3; // Set to 0 for infinite loop, reduced to 3 for testing

echo "Starting data generation loop...\n";
while ($iterations < $maxIterations || $maxIterations === 0) {
    $timestamp = new DateTime;
    echo 'Iteration '.($iterations + 1).' at '.$timestamp->format('Y-m-d H:i:s')."\n";

    // Generate data points for each measurement
    $dataPoints = [];
    foreach ($measurements as $measurement => $measurementConfig) {
        foreach ($measurementConfig['tags']['host'] as $host) {
            // Create a base set of tags for this host
            $baseTags = ['host' => $host];

            // Add other tags randomly
            foreach ($measurementConfig['tags'] as $tag => $values) {
                if ($tag !== 'host') {
                    $baseTags[$tag] = $values[array_rand($values)];
                }
            }

            // Generate fields with some variance
            $fields = [];
            foreach ($measurementConfig['fields'] as $field => $config) {
                $fields[$field] = generateValue($config['min'], $config['max'], $config['type']);
            }

            $dataPoints[$measurement][] = new DataPoint($measurement, $fields, $baseTags, $timestamp);
        }
    }

    // Write data to each database
    foreach ($databases as $name => $db) {
        try {
            echo "Writing to $name...\n";
            foreach ($dataPoints as $measurement => $points) {
                foreach ($points as $point) {
                    $result = $db->write($point->getMeasurement(), $point->getFields(), $point->getTags(), $point->getTimestamp());
                    if (! $result) {
                        echo "Failed to write to $name for measurement $measurement\n";
                    }
                }
            }
            echo "Successfully wrote data to $name.\n";
        } catch (\Exception $e) {
            echo "Error writing to $name: ".$e->getMessage()."\n";
        }
    }

    $iterations++;
    echo "Sleeping for $interval seconds...\n";
    sleep($interval);
}

echo "Data generation complete.\n";

// Close database connections
foreach ($databases as $name => $db) {
    echo "Closing connection to $name...\n";
    $db->close();
}

echo "All connections closed.\n";

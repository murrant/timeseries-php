#!/usr/bin/env php
<?php

use TimeSeriesPhp\Core\Data\QueryResult;
use TimeSeriesPhp\Core\Query\Query;
use TimeSeriesPhp\Exceptions\TSDBException;
use TimeSeriesPhp\TSDB;

/**
 * Script to verify sample data written by generate_sample_data.php script.
 *
 * This script will connect to each database and query the data to verify
 * that the sample data was written correctly.
 */

// Set up error reporting
error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
ini_set('display_errors', 1);

// Parse command line arguments
$options = getopt('hv', ['help', 'verbose', 'measurement:', 'host:', 'since:']);

// Display usage information if --help is provided
if (isset($options['h']) || isset($options['help'])) {
    echo "Usage: php verify_sample_data.php [options]\n";
    echo "Options:\n";
    echo "  --measurement=<name>   Filter by specific measurement (cpu, memory, network, disk)\n";
    echo "  --host=<name>         Filter by specific host (server1, server2, server3)\n";
    echo "  --since=<duration>    Show data from last X time (e.g., '1h', '30m', '1d')\n";
    echo "  -v, --verbose         Show detailed output\n";
    echo "  --help, -h            Display this help message\n";
    exit(0);
}

$verbose = isset($options['v']) || isset($options['verbose']);
$measurementFilter = $options['measurement'] ?? null;
$hostFilter = $options['host'] ?? null;
$sinceFilter = $options['since'] ?? '1h'; // Default to last hour

// Autoload dependencies
require_once __DIR__.'/../vendor/autoload.php';

// Configuration for each database (updated for localhost connections)
$config = [
    'influxdb' => [
        'driver' => 'influxdb',
        'config' => [
            'url' => 'http://localhost:8086',
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
            'rrdcached_enabled' => true,
            'rrdcached_address' => 'localhost:42217',
            'persistent_process' => true,
            'command_timeout' => 180,
            'default_step' => 60,
            'debug' => true,
        ],
    ],
];

// Measurements to verify
$measurements = ['cpu', 'memory', 'network', 'disk'];
$hosts = ['server1', 'server2', 'server3'];

// Filter measurements if specified
if ($measurementFilter && in_array($measurementFilter, $measurements)) {
    $measurements = [$measurementFilter];
}

// Connect to each database
$databases = [];
foreach ($config as $name => $dbConfig) {
    try {
        echo "Connecting to $name...\n";
        $databases[$name] = TSDB::start($dbConfig['driver'], $dbConfig['config']);
        echo "✓ Connected to $name successfully.\n";
    } catch (TSDBException $e) {
        echo "✗ Error connecting to $name: ".$e->getMessage()."\n";
    }
}

if (empty($databases)) {
    echo "No databases available for verification.\n";
    exit(1);
}

echo "\n".str_repeat('=', 60)."\n";
echo "VERIFYING SAMPLE DATA\n";
echo str_repeat('=', 60)."\n";

// Function to format numbers for display
function formatNumber($value): string
{
    if (is_null($value)) {
        return 'null';
    }

    return is_float($value) ? number_format($value, 2) : (string) $value;
}

// Function to display query results
function displayResults(string $dbName, string $measurement, QueryResult $result, bool $verbose): void
{
    if ($result->isEmpty()) {
        echo "  └─ No data found\n";

        return;
    }

    $series = $result->getSeries();
    $totalPoints = 0;

    // Count total data points across all series
    foreach ($series as $points) {
        $totalPoints += count($points);
    }

    echo "  └─ Found $totalPoints data points across ".count($series)." field(s)\n";

    if ($verbose && $totalPoints > 0) {
        echo "     Recent data points:\n";
        $displayCount = min(3, count($series));
        $fieldNames = array_keys($series);

        for ($i = 0; $i < $displayCount; $i++) {
            $fieldName = $fieldNames[$i];
            $points = $series[$fieldName];

            if (! empty($points)) {
                $recentPoints = array_slice($points, -3); // Get last 3 points
                echo "     • Field '$fieldName':\n";

                foreach ($recentPoints as $point) {
                    $timestamp = $point['date'];
                    $value = formatNumber($point['value']);
                    echo "       $timestamp: $value\n";
                }
            }
        }

        if (count($series) > $displayCount) {
            echo '     ... and '.(count($series) - $displayCount)." more fields\n";
        }

        // Show metadata if available
        $metadata = $result->getMetadata();
        if (! empty($metadata)) {
            echo '     Metadata: '.json_encode($metadata)."\n";
        }
    }
}

// Function to create and execute queries for each measurement
function verifyMeasurement(string $dbName, $db, string $measurement, ?string $hostFilter, string $sinceFilter, bool $verbose): array
{
    $results = [];

    try {
        // Basic query to get recent data
        $query = new Query($measurement);
        $query->latest($sinceFilter)
            ->orderByTime('DESC')
            ->limit(100);

        // Apply host filter if specified
        if ($hostFilter) {
            $query->where('host', '=', $hostFilter);
        }

        echo "  Querying $measurement data...\n";
        $data = $db->query($query);
        $results['basic'] = $data;

        displayResults($dbName, $measurement, $data, $verbose);

        // Aggregation query - get average values per host
        if (! $hostFilter) {
            $aggQuery = new Query($measurement);
            $aggQuery->latest($sinceFilter)
                ->groupBy(['host'], '10m')
                ->avg('*', 'avg_value')
                ->count('*', 'data_points');

            echo "  Querying aggregated $measurement data...\n";
            $aggData = $db->query($aggQuery);
            $results['aggregated'] = $aggData;

            if ($verbose && ! $aggData->isEmpty()) {
                echo "  └─ Aggregated results:\n";
                $series = $aggData->getSeries();
                foreach ($series as $field => $points) {
                    echo "     • Field '$field': ".count($points)." aggregated points\n";
                    if (! empty($points)) {
                        $latest = end($points);
                        echo '       Latest value: '.formatNumber($latest['value'])."\n";
                    }
                }
            }
        }

        // Statistical query - get min/max values
        $statsQuery = new Query($measurement);
        $statsQuery->latest($sinceFilter);

        // Add different aggregations based on measurement type
        switch ($measurement) {
            case 'cpu':
                $statsQuery->min('usage', 'min_usage')
                    ->max('usage', 'max_usage')
                    ->avg('usage', 'avg_usage');
                break;
            case 'memory':
                $statsQuery->min('used', 'min_used')
                    ->max('used', 'max_used')
                    ->avg('used', 'avg_used');
                break;
            case 'network':
                $statsQuery->sum('bytes_in', 'total_bytes_in')
                    ->sum('bytes_out', 'total_bytes_out');
                break;
            case 'disk':
                $statsQuery->avg('used', 'avg_used')
                    ->sum('read_ops', 'total_read_ops')
                    ->sum('write_ops', 'total_write_ops');
                break;
        }

        echo "  Querying $measurement statistics...\n";
        $statsData = $db->query($statsQuery);
        $results['statistics'] = $statsData;

        if ($verbose && ! $statsData->isEmpty()) {
            echo "  └─ Statistics:\n";
            $series = $statsData->getSeries();
            foreach ($series as $field => $points) {
                if (! empty($points)) {
                    $value = $points[0]['value'] ?? null;
                    echo "     • $field: ".formatNumber($value)."\n";
                }
            }
        }

    } catch (TSDBException $e) {
        echo "  ✗ Error querying $measurement: ".$e->getMessage()."\n";
        $results['error'] = $e->getMessage();
    }

    return $results;
}

// Main verification loop
$overallResults = [];

foreach ($databases as $dbName => $db) {
    echo "\n".str_repeat('-', 40)."\n";
    echo 'VERIFYING: '.strtoupper($dbName)."\n";
    echo str_repeat('-', 40)."\n";

    $dbResults = [];

    foreach ($measurements as $measurement) {
        echo "\n📊 Checking $measurement measurement:\n";
        $measurementResults = verifyMeasurement($dbName, $db, $measurement, $hostFilter, $sinceFilter, $verbose);
        $dbResults[$measurement] = $measurementResults;
    }

    // Summary for this database
    echo "\n🔍 Summary for $dbName:\n";
    $totalPoints = 0;
    $measurementsWithData = 0;

    foreach ($dbResults as $measurement => $results) {
        if (isset($results['basic'])) {
            $queryResult = $results['basic'];
            $count = 0;

            // Count total data points across all series
            if ($queryResult instanceof QueryResult) {
                $series = $queryResult->getSeries();
                foreach ($series as $points) {
                    $count += count($points);
                }
            }

            $totalPoints += $count;
            if ($count > 0) {
                $measurementsWithData++;
            }
            echo "  • $measurement: $count data points\n";
        } elseif (isset($results['error'])) {
            echo "  • $measurement: ERROR - ".$results['error']."\n";
        }
    }

    echo "  📈 Total: $totalPoints points across $measurementsWithData measurements\n";
    $overallResults[$dbName] = $dbResults;
}

// Overall summary
echo "\n".str_repeat('=', 60)."\n";
echo "OVERALL VERIFICATION SUMMARY\n";
echo str_repeat('=', 60)."\n";

$totalDatabases = count($overallResults);
$successfulDatabases = 0;
$grandTotalPoints = 0;

foreach ($overallResults as $dbName => $dbResults) {
    $dbTotal = 0;
    $dbMeasurements = 0;
    $hasErrors = false;

    foreach ($dbResults as $results) {
        if (isset($results['basic'])) {
            $queryResult = $results['basic'];
            $count = 0;

            // Count total data points across all series
            if ($queryResult instanceof QueryResult) {
                $series = $queryResult->getSeries();
                foreach ($series as $points) {
                    $count += count($points);
                }
            }

            $dbTotal += $count;
            if ($count > 0) {
                $dbMeasurements++;
            }
        } elseif (isset($results['error'])) {
            $hasErrors = true;
        }
    }

    $status = $hasErrors ? '⚠️  PARTIAL' : ($dbTotal > 0 ? '✅ SUCCESS' : '❌ NO DATA');
    echo "• $dbName: $status - $dbTotal points, $dbMeasurements measurements\n";

    if (! $hasErrors && $dbTotal > 0) {
        $successfulDatabases++;
    }
    $grandTotalPoints += $dbTotal;
}

echo "\n📊 Final Results:\n";
echo "• Databases tested: $totalDatabases\n";
echo "• Successful databases: $successfulDatabases\n";
echo "• Total data points found: $grandTotalPoints\n";

$successRate = $totalDatabases > 0 ? ($successfulDatabases / $totalDatabases) * 100 : 0;
echo '• Success rate: '.number_format($successRate, 1)."%\n";

if ($successfulDatabases === $totalDatabases && $grandTotalPoints > 0) {
    echo "\n🎉 All databases verified successfully!\n";
} elseif ($successfulDatabases > 0) {
    echo "\n⚠️  Partial success - some databases have issues.\n";
} else {
    echo "\n❌ Verification failed - no data found or all databases failed.\n";
}

// Close database connections
echo "\nClosing database connections...\n";
foreach ($databases as $name => $db) {
    try {
        $db->close();
        echo "✓ Closed connection to $name\n";
    } catch (TSDBException $e) {
        echo "⚠️  Warning: Could not properly close $name: ".$e->getMessage()."\n";
    }
}

echo "\nVerification complete.\n";

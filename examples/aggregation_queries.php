<?php

require_once __DIR__.'/../vendor/autoload.php';

use TimeSeriesPhp\Core\Data\DataPoint;
use TimeSeriesPhp\Core\Query\Query;
use TimeSeriesPhp\Core\TSDBFactory;
use TimeSeriesPhp\Exceptions\Config\ConfigurationException;
use TimeSeriesPhp\Exceptions\Driver\DriverException;

/**
 * Aggregation Queries Example
 *
 * This example demonstrates various aggregation techniques and time-based grouping
 * across different time series database drivers.
 */
echo "TimeSeriesPhp Aggregation Queries Example\n";
echo "========================================\n\n";

// Choose a driver to use for this example
// Options: 'influxdb', 'prometheus', 'graphite', 'rrdtool'
$driver = 'influxdb';

// Step 1: Configure and connect to the database
echo "Step 1: Configuring and connecting to {$driver}...\n";

try {
    // Create configuration based on the selected driver
    $config = createConfig($driver);

    // Create database instance
    $db = TSDBFactory::create($driver, $config);
    echo "Successfully connected to {$driver}!\n";
} catch (ConfigurationException|DriverException $e) {
    echo "Error: {$e->getMessage()}\n";
    exit(1);
}

// Step 2: Generate and write sample data for aggregation
echo "\nStep 2: Generating and writing sample data...\n";

// Generate data for the last 24 hours at 5-minute intervals
$dataPoints = generateSampleData($driver, 24, 5);

// Write the data
try {
    if ($db->writeBatch($dataPoints)) {
        echo 'Successfully wrote '.count($dataPoints)." data points.\n";
    } else {
        echo "Failed to write data points.\n";
    }
} catch (\Exception $e) {
    echo "Error writing data: {$e->getMessage()}\n";
}

// Step 3: Basic aggregation queries
echo "\nStep 3: Basic aggregation queries...\n";

// Time range for all queries: last 24 hours
$startTime = new DateTime;
$startTime->modify('-24 hours');
$endTime = new DateTime;

// 3.1: Simple average aggregation
echo "\n3.1: Simple average aggregation\n";
$avgQuery = new Query('server_metrics');
$avgQuery->select(['cpu_usage'])
    ->timeRange($startTime, $endTime)
    ->avg('cpu_usage', 'avg_cpu');

try {
    $result = $db->query($avgQuery);
    echo "Average CPU usage over the last 24 hours:\n";
    printQueryResult($result);
} catch (\Exception $e) {
    echo "Error executing average query: {$e->getMessage()}\n";
}

// 3.2: Min/Max aggregation
echo "\n3.2: Min/Max aggregation\n";
$minMaxQuery = new Query('server_metrics');
$minMaxQuery->select(['cpu_usage'])
    ->timeRange($startTime, $endTime)
    ->min('cpu_usage', 'min_cpu')
    ->max('cpu_usage', 'max_cpu');

try {
    $result = $db->query($minMaxQuery);
    echo "Min/Max CPU usage over the last 24 hours:\n";
    printQueryResult($result);
} catch (\Exception $e) {
    echo "Error executing min/max query: {$e->getMessage()}\n";
}

// 3.3: Count and sum aggregation
echo "\n3.3: Count and sum aggregation\n";
$countSumQuery = new Query('server_metrics');
$countSumQuery->select(['memory_usage'])
    ->timeRange($startTime, $endTime)
    ->count('memory_usage', 'count_memory')
    ->sum('memory_usage', 'sum_memory');

try {
    $result = $db->query($countSumQuery);
    echo "Count and sum of memory usage over the last 24 hours:\n";
    printQueryResult($result);
} catch (\Exception $e) {
    echo "Error executing count/sum query: {$e->getMessage()}\n";
}

// Step 4: Time-based grouping
echo "\nStep 4: Time-based grouping...\n";

// 4.1: Hourly aggregation
echo "\n4.1: Hourly aggregation\n";
$hourlyQuery = new Query('server_metrics');
$hourlyQuery->select(['cpu_usage'])
    ->timeRange($startTime, $endTime)
    ->groupByTime('1h')
    ->avg('cpu_usage', 'avg_cpu');

try {
    $result = $db->query($hourlyQuery);
    echo "Hourly average CPU usage:\n";
    printQueryResult($result);
} catch (\Exception $e) {
    echo "Error executing hourly query: {$e->getMessage()}\n";
}

// 4.2: Different time windows
echo "\n4.2: Different time windows\n";

// 15-minute intervals
$fifteenMinQuery = new Query('server_metrics');
$fifteenMinQuery->select(['cpu_usage'])
    ->timeRange($startTime, $endTime)
    ->groupByTime('15m')
    ->avg('cpu_usage', 'avg_cpu');

try {
    $result = $db->query($fifteenMinQuery);
    echo "15-minute average CPU usage (showing first 5 results):\n";
    printQueryResult($result, 5);
} catch (\Exception $e) {
    echo "Error executing 15-minute query: {$e->getMessage()}\n";
}

// 6-hour intervals
$sixHourQuery = new Query('server_metrics');
$sixHourQuery->select(['cpu_usage'])
    ->timeRange($startTime, $endTime)
    ->groupByTime('6h')
    ->avg('cpu_usage', 'avg_cpu');

try {
    $result = $db->query($sixHourQuery);
    echo "6-hour average CPU usage:\n";
    printQueryResult($result);
} catch (\Exception $e) {
    echo "Error executing 6-hour query: {$e->getMessage()}\n";
}

// Step 5: Filling missing values
echo "\nStep 5: Filling missing values...\n";

// Generate sparse data with gaps
$sparseDataPoints = generateSparseData($driver, 24, 5);

// Write the sparse data
try {
    if ($db->writeBatch($sparseDataPoints)) {
        echo 'Successfully wrote '.count($sparseDataPoints)." sparse data points.\n";
    } else {
        echo "Failed to write sparse data points.\n";
    }
} catch (\Exception $e) {
    echo "Error writing sparse data: {$e->getMessage()}\n";
}

// 5.1: No filling (null values for missing data)
echo "\n5.1: No filling (null values for missing data)\n";
$noFillQuery = new Query('sparse_metrics');
$noFillQuery->select(['value'])
    ->timeRange($startTime, $endTime)
    ->groupByTime('1h')
    ->avg('value', 'avg_value')
    ->fillNull();

try {
    $result = $db->query($noFillQuery);
    echo "Hourly average with null filling:\n";
    printQueryResult($result, 5);
} catch (\Exception $e) {
    echo "Error executing no-fill query: {$e->getMessage()}\n";
}

// 5.2: Fill with previous value
echo "\n5.2: Fill with previous value\n";
$previousFillQuery = new Query('sparse_metrics');
$previousFillQuery->select(['value'])
    ->timeRange($startTime, $endTime)
    ->groupByTime('1h')
    ->avg('value', 'avg_value')
    ->fillPrevious();

try {
    $result = $db->query($previousFillQuery);
    echo "Hourly average with previous value filling:\n";
    printQueryResult($result, 5);
} catch (\Exception $e) {
    echo "Error executing previous-fill query: {$e->getMessage()}\n";
}

// 5.3: Fill with a specific value
echo "\n5.3: Fill with a specific value\n";
$valueFillQuery = new Query('sparse_metrics');
$valueFillQuery->select(['value'])
    ->timeRange($startTime, $endTime)
    ->groupByTime('1h')
    ->avg('value', 'avg_value')
    ->fillValue(0);

try {
    $result = $db->query($valueFillQuery);
    echo "Hourly average with zero filling:\n";
    printQueryResult($result, 5);
} catch (\Exception $e) {
    echo "Error executing value-fill query: {$e->getMessage()}\n";
}

// Step 6: Multi-dimensional aggregation
echo "\nStep 6: Multi-dimensional aggregation...\n";

// 6.1: Group by tag
echo "\n6.1: Group by tag\n";
$tagGroupQuery = new Query('server_metrics');
$tagGroupQuery->select(['cpu_usage'])
    ->timeRange($startTime, $endTime)
    ->groupBy(['host'])
    ->avg('cpu_usage', 'avg_cpu');

try {
    $result = $db->query($tagGroupQuery);
    echo "Average CPU usage grouped by host:\n";
    printQueryResult($result);
} catch (\Exception $e) {
    echo "Error executing tag group query: {$e->getMessage()}\n";
}

// 6.2: Group by tag and time
echo "\n6.2: Group by tag and time\n";
$tagTimeGroupQuery = new Query('server_metrics');
$tagTimeGroupQuery->select(['cpu_usage'])
    ->timeRange($startTime, $endTime)
    ->groupBy(['host', 'region'], '1h')
    ->avg('cpu_usage', 'avg_cpu');

try {
    $result = $db->query($tagTimeGroupQuery);
    echo "Hourly average CPU usage grouped by host and region (showing first 5 results):\n";
    printQueryResult($result, 5);
} catch (\Exception $e) {
    echo "Error executing tag and time group query: {$e->getMessage()}\n";
}

// Step 7: Advanced aggregations
echo "\nStep 7: Advanced aggregations...\n";

// 7.1: Percentile calculation
echo "\n7.1: Percentile calculation\n";
$percentileQuery = new Query('server_metrics');
$percentileQuery->select(['cpu_usage'])
    ->timeRange($startTime, $endTime)
    ->percentile('cpu_usage', 95, 'p95_cpu')
    ->percentile('cpu_usage', 99, 'p99_cpu');

try {
    $result = $db->query($percentileQuery);
    echo "95th and 99th percentile of CPU usage:\n";
    printQueryResult($result);
} catch (\Exception $e) {
    echo "Error executing percentile query: {$e->getMessage()}\n";
}

// 7.2: Standard deviation
echo "\n7.2: Standard deviation\n";
$stddevQuery = new Query('server_metrics');
$stddevQuery->select(['cpu_usage'])
    ->timeRange($startTime, $endTime)
    ->stddev('cpu_usage', 'stddev_cpu');

try {
    $result = $db->query($stddevQuery);
    echo "Standard deviation of CPU usage:\n";
    printQueryResult($result);
} catch (\Exception $e) {
    echo "Error executing standard deviation query: {$e->getMessage()}\n";
}

// 7.3: Moving average
echo "\n7.3: Moving average\n";
// Note: Moving averages might be implemented differently across drivers
// This example uses a raw query for InfluxDB
if ($driver === 'influxdb') {
    try {
        $movingAvgResult = $db->rawQuery('from(bucket: "example-bucket")
          |> range(start: -24h)
          |> filter(fn: (r) => r._measurement == "server_metrics" and r._field == "cpu_usage")
          |> timedMovingAverage(every: 1h, period: 3h)');

        echo "3-hour moving average of CPU usage (InfluxDB raw query):\n";
        printQueryResult($movingAvgResult, 5);
    } catch (\Exception $e) {
        echo "Error executing moving average query: {$e->getMessage()}\n";
    }
} else {
    echo "Moving average example is only implemented for InfluxDB in this example.\n";
}

// Step 8: Comparing aggregation methods
echo "\nStep 8: Comparing aggregation methods...\n";

// Create a query that compares different aggregation methods
$comparisonQuery = new Query('server_metrics');
$comparisonQuery->select(['cpu_usage'])
    ->timeRange($startTime, $endTime)
    ->groupByTime('6h')
    ->avg('cpu_usage', 'avg_cpu')
    ->min('cpu_usage', 'min_cpu')
    ->max('cpu_usage', 'max_cpu')
    ->stddev('cpu_usage', 'stddev_cpu');

try {
    $result = $db->query($comparisonQuery);
    echo "Comparison of different aggregation methods (6-hour intervals):\n";
    printQueryResult($result);
} catch (\Exception $e) {
    echo "Error executing comparison query: {$e->getMessage()}\n";
}

// Step 9: Raw aggregation queries (driver-specific)
echo "\nStep 9: Raw aggregation queries (driver-specific)...\n";

switch ($driver) {
    case 'influxdb':
        try {
            $rawResult = $db->rawQuery('from(bucket: "example-bucket")
              |> range(start: -24h)
              |> filter(fn: (r) => r._measurement == "server_metrics" and r._field == "cpu_usage")
              |> aggregateWindow(every: 1h, fn: mean)
              |> yield(name: "mean")');

            echo "InfluxDB Flux query for hourly averages:\n";
            printQueryResult($rawResult, 5);
        } catch (\Exception $e) {
            echo "Error executing InfluxDB raw query: {$e->getMessage()}\n";
        }
        break;

    case 'prometheus':
        try {
            $rawResult = $db->rawQuery('avg_over_time(server_metrics{job="aggregation_example"}[1h])');

            echo "Prometheus PromQL query for hourly averages:\n";
            printQueryResult($rawResult, 5);
        } catch (\Exception $e) {
            echo "Error executing Prometheus raw query: {$e->getMessage()}\n";
        }
        break;

    case 'graphite':
        try {
            $rawResult = $db->rawQuery('summarize(servers.*.cpu_usage, "1hour", "avg")');

            echo "Graphite query for hourly averages:\n";
            printQueryResult($rawResult, 5);
        } catch (\Exception $e) {
            echo "Error executing Graphite raw query: {$e->getMessage()}\n";
        }
        break;

    case 'rrdtool':
        try {
            $rawResult = $db->rawQuery('fetch server_metrics.rrd AVERAGE --start -24h --end now');

            echo "RRDtool query for averages:\n";
            printQueryResult($rawResult, 5);
        } catch (\Exception $e) {
            echo "Error executing RRDtool raw query: {$e->getMessage()}\n";
        }
        break;
}

// Close the connection
$db->close();
echo "\nConnection closed.\n";

echo "\nExample completed successfully!\n";

/**
 * Helper function to create a configuration for the specified driver
 */
function createConfig($driver)
{
    switch ($driver) {
        case 'influxdb':
            // For InfluxDB, try to read token from file or use a default
            $token = file_exists(__DIR__.'/.influx_db_token')
                ? trim(file_get_contents(__DIR__.'/.influx_db_token'))
                : 'your-token';

            return new \TimeSeriesPhp\Drivers\InfluxDB\InfluxDBConfig([
                'url' => 'http://localhost:8086',
                'token' => $token,
                'org' => 'example-org',
                'bucket' => 'example-bucket',
            ]);

        case 'prometheus':
            return new \TimeSeriesPhp\Drivers\Prometheus\PrometheusConfig([
                'url' => 'http://localhost:9090',
                // Add authentication if needed
                // 'username' => 'your-username',
                // 'password' => 'your-password',
            ]);

        case 'graphite':
            return new \TimeSeriesPhp\Drivers\Graphite\GraphiteConfig([
                'host' => 'localhost',
                'port' => 2003,
                'protocol' => 'tcp',
                'prefix' => 'servers.',
            ]);

        case 'rrdtool':
            $rrdPath = __DIR__.'/rrd_files';
            if (! is_dir($rrdPath)) {
                mkdir($rrdPath, 0755, true);
            }

            // For RRDtool, create the necessary RRD files
            $rrdFile = $rrdPath.'/server_metrics.rrd';
            if (! file_exists($rrdFile)) {
                $rrdtool = `which rrdtool`;
                if (! empty($rrdtool)) {
                    $cmd = trim($rrdtool)." create {$rrdFile} --step 300 ".
                           'DS:cpu_usage:GAUGE:600:0:100 '.
                           'DS:memory_usage:GAUGE:600:0:100 '.
                           'RRA:AVERAGE:0.5:1:576 '.  // 1 day of 5-minute data
                           'RRA:AVERAGE:0.5:12:336';   // 1 week of hourly data
                    exec($cmd);
                }
            }

            return new \TimeSeriesPhp\Drivers\RRDtool\RRDtoolConfig([
                'path' => $rrdPath,
                'rrdtool_bin' => '/usr/bin/rrdtool',
                'default_step' => 300, // 5 minutes
            ]);

        default:
            throw new \InvalidArgumentException("Unsupported driver: {$driver}");
    }
}

/**
 * Generate sample data for aggregation examples
 */
function generateSampleData($driver, $hours = 24, $intervalMinutes = 5)
{
    $dataPoints = [];
    $startTime = new DateTime;
    $startTime->modify("-{$hours} hours");

    $intervals = ($hours * 60) / $intervalMinutes;

    for ($i = 0; $i < $intervals; $i++) {
        $timestamp = clone $startTime;
        $timestamp->modify("+{$i} minutes * {$intervalMinutes}");

        // Generate some realistic-looking data with patterns
        // Base value with daily pattern (higher during day, lower at night)
        $hourOfDay = (int) $timestamp->format('G');
        $dayFactor = ($hourOfDay >= 8 && $hourOfDay <= 18) ? 1.5 : 0.8;

        // Add some randomness and a sine wave pattern
        $cpuValue = 40 * $dayFactor + 20 * sin($i / 30) + mt_rand(-10, 10);
        $cpuValue = max(5, min(95, $cpuValue)); // Keep between 5-95%

        $memoryValue = 50 * $dayFactor + 10 * cos($i / 20) + mt_rand(-5, 15);
        $memoryValue = max(10, min(90, $memoryValue)); // Keep between 10-90%

        // Create data points for different hosts
        for ($host = 1; $host <= 3; $host++) {
            // Add some host-specific variation
            $hostCpuFactor = 1 + ($host - 2) * 0.2; // Host 1: 0.8, Host 2: 1.0, Host 3: 1.2
            $hostCpuValue = max(5, min(95, $cpuValue * $hostCpuFactor));

            $hostMemoryFactor = 1 + ($host - 2) * 0.15;
            $hostMemoryValue = max(10, min(90, $memoryValue * $hostMemoryFactor));

            // Create the data point based on the driver
            switch ($driver) {
                case 'influxdb':
                case 'prometheus':
                    $dataPoint = new DataPoint(
                        'server_metrics',
                        [
                            'cpu_usage' => $hostCpuValue,
                            'memory_usage' => $hostMemoryValue,
                        ],
                        [
                            'host' => "server{$host}",
                            'region' => ($host % 2 === 0) ? 'us-west' : 'us-east',
                            'environment' => ($host % 3 === 0) ? 'production' : 'staging',
                        ],
                        $timestamp
                    );

                    // Add job tag for Prometheus
                    if ($driver === 'prometheus') {
                        $dataPoint = new DataPoint(
                            'server_metrics',
                            [
                                'cpu_usage' => $hostCpuValue,
                                'memory_usage' => $hostMemoryValue,
                            ],
                            [
                                'host' => "server{$host}",
                                'region' => ($host % 2 === 0) ? 'us-west' : 'us-east',
                                'environment' => ($host % 3 === 0) ? 'production' : 'staging',
                                'job' => 'aggregation_example',
                            ],
                            $timestamp
                        );
                    }
                    break;

                case 'graphite':
                    // For Graphite, use dot notation in the measurement name
                    $dataPoint = new DataPoint(
                        "server{$host}.cpu_usage",
                        ['value' => $hostCpuValue],
                        [],
                        $timestamp
                    );

                    // Add a second data point for memory usage
                    $dataPoints[] = new DataPoint(
                        "server{$host}.memory_usage",
                        ['value' => $hostMemoryValue],
                        [],
                        $timestamp
                    );
                    break;

                case 'rrdtool':
                    // For RRDtool, we'll use a single RRD file with multiple data sources
                    $dataPoint = new DataPoint(
                        'server_metrics',
                        [
                            'cpu_usage' => $hostCpuValue,
                            'memory_usage' => $hostMemoryValue,
                        ],
                        [],
                        $timestamp
                    );
                    break;

                default:
                    throw new \InvalidArgumentException("Unsupported driver: {$driver}");
            }

            $dataPoints[] = $dataPoint;
        }
    }

    return $dataPoints;
}

/**
 * Generate sparse data with intentional gaps for fill examples
 */
function generateSparseData($driver, $hours = 24, $intervalMinutes = 5)
{
    $dataPoints = [];
    $startTime = new DateTime;
    $startTime->modify("-{$hours} hours");

    $intervals = ($hours * 60) / $intervalMinutes;

    for ($i = 0; $i < $intervals; $i++) {
        // Create gaps in the data (skip some intervals)
        if ($i % 4 === 0 || $i % 7 === 0) {
            continue;
        }

        $timestamp = clone $startTime;
        $timestamp->modify("+{$i} minutes * {$intervalMinutes}");

        // Generate a value with some pattern
        $value = 50 + 25 * sin($i / 20) + mt_rand(-10, 10);

        // Create the data point based on the driver
        switch ($driver) {
            case 'influxdb':
            case 'prometheus':
                $dataPoint = new DataPoint(
                    'sparse_metrics',
                    ['value' => $value],
                    [
                        'host' => 'server1',
                        'type' => 'sparse',
                    ],
                    $timestamp
                );

                // Add job tag for Prometheus
                if ($driver === 'prometheus') {
                    $dataPoint = new DataPoint(
                        'sparse_metrics',
                        ['value' => $value],
                        [
                            'host' => 'server1',
                            'type' => 'sparse',
                            'job' => 'aggregation_example',
                        ],
                        $timestamp
                    );
                }
                break;

            case 'graphite':
                $dataPoint = new DataPoint(
                    'sparse_metrics.value',
                    ['value' => $value],
                    [],
                    $timestamp
                );
                break;

            case 'rrdtool':
                // For RRDtool, ensure the RRD file exists
                static $rrdFileCreated = false;
                if (! $rrdFileCreated) {
                    $rrdPath = __DIR__.'/rrd_files';
                    $rrdFile = $rrdPath.'/sparse_metrics.rrd';

                    if (! file_exists($rrdFile)) {
                        $rrdtool = `which rrdtool`;
                        if (! empty($rrdtool)) {
                            $cmd = trim($rrdtool)." create {$rrdFile} --step 300 ".
                                   'DS:value:GAUGE:600:0:100 '.
                                   'RRA:AVERAGE:0.5:1:576';  // 1 day of 5-minute data
                            exec($cmd);
                        }
                    }

                    $rrdFileCreated = true;
                }

                $dataPoint = new DataPoint(
                    'sparse_metrics',
                    ['value' => $value],
                    [],
                    $timestamp
                );
                break;

            default:
                throw new \InvalidArgumentException("Unsupported driver: {$driver}");
        }

        $dataPoints[] = $dataPoint;
    }

    return $dataPoints;
}

/**
 * Helper function to print query results
 */
function printQueryResult($result, $limit = null)
{
    $series = $result->getSeries();

    if (empty($series)) {
        echo "No results found.\n";

        return;
    }

    $count = 0;
    foreach ($series as $row) {
        if ($limit !== null && $count >= $limit) {
            echo "... (more results available)\n";
            break;
        }

        // Format the output
        $output = '';
        foreach ($row as $key => $value) {
            if ($key === 'time') {
                // Format timestamp
                if ($value instanceof DateTime) {
                    $value = $value->format('Y-m-d H:i:s');
                }
            } elseif (is_float($value)) {
                // Format float values
                $value = round($value, 2);
            }

            $output .= "{$key}: {$value}, ";
        }

        echo rtrim($output, ', ')."\n";
        $count++;
    }
}

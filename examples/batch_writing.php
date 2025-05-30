<?php

require_once __DIR__.'/../vendor/autoload.php';

use TimeSeriesPhp\Core\Data\DataPoint;
use TimeSeriesPhp\Core\TSDBFactory;
use TimeSeriesPhp\Exceptions\Config\ConfigurationException;
use TimeSeriesPhp\Exceptions\Driver\DriverException;

/**
 * Batch Writing Example
 *
 * This example demonstrates efficient batch writing techniques for high-volume data
 * scenarios across different time series database drivers.
 */
echo "TimeSeriesPhp Batch Writing Example\n";
echo "==================================\n\n";

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

// Step 2: Simple batch writing
echo "\nStep 2: Simple batch writing...\n";

// Create a batch of data points
$dataPoints = [];
$startTime = new DateTime;
$startTime->modify('-1 hour');

// Generate 100 data points at 1-minute intervals
for ($i = 0; $i < 100; $i++) {
    $timestamp = clone $startTime;
    $timestamp->modify("+{$i} minute");

    // Create a data point with appropriate structure for the selected driver
    $dataPoint = createDataPoint($driver, $timestamp, $i);
    $dataPoints[] = $dataPoint;
}

// Write the batch
try {
    $startTime = microtime(true);
    $success = $db->writeBatch($dataPoints);
    $endTime = microtime(true);
    $duration = round(($endTime - $startTime) * 1000, 2);

    if ($success) {
        echo 'Successfully wrote '.count($dataPoints)." data points in batch.\n";
        echo "Batch write took {$duration}ms.\n";
    } else {
        echo "Failed to write data points in batch.\n";
    }
} catch (\Exception $e) {
    echo "Error writing batch: {$e->getMessage()}\n";
}

// Step 3: Chunked batch writing for large datasets
echo "\nStep 3: Chunked batch writing for large datasets...\n";

// Number of data points to generate
$totalPoints = 1000;
// Chunk size for batch writes
$chunkSize = 100;
// Total number of chunks
$totalChunks = ceil($totalPoints / $chunkSize);

echo "Generating {$totalPoints} data points and writing in chunks of {$chunkSize}...\n";

$startTime = new DateTime;
$startTime->modify('-1 day');
$totalDuration = 0;
$successCount = 0;

for ($chunk = 0; $chunk < $totalChunks; $chunk++) {
    $dataPoints = [];
    $chunkStart = $chunk * $chunkSize;
    $chunkEnd = min(($chunk + 1) * $chunkSize, $totalPoints);

    // Generate data points for this chunk
    for ($i = $chunkStart; $i < $chunkEnd; $i++) {
        $timestamp = clone $startTime;
        $timestamp->modify("+{$i} minute");

        $dataPoint = createDataPoint($driver, $timestamp, $i);
        $dataPoints[] = $dataPoint;
    }

    // Write this chunk
    try {
        $chunkStartTime = microtime(true);
        $success = $db->writeBatch($dataPoints);
        $chunkEndTime = microtime(true);
        $chunkDuration = round(($chunkEndTime - $chunkStartTime) * 1000, 2);
        $totalDuration += $chunkDuration;

        if ($success) {
            $successCount += count($dataPoints);
            echo 'Chunk '.($chunk + 1)."/{$totalChunks}: Successfully wrote ".count($dataPoints)." data points in {$chunkDuration}ms.\n";
        } else {
            echo 'Chunk '.($chunk + 1)."/{$totalChunks}: Failed to write data points.\n";
        }
    } catch (\Exception $e) {
        echo 'Error writing chunk '.($chunk + 1).": {$e->getMessage()}\n";
    }
}

echo "\nSummary: Successfully wrote {$successCount}/{$totalPoints} data points in {$totalDuration}ms.\n";
echo 'Average time per chunk: '.round($totalDuration / $totalChunks, 2)."ms.\n";
echo 'Average time per data point: '.round($totalDuration / $successCount, 2)."ms.\n";

// Step 4: Buffered writing example
echo "\nStep 4: Buffered writing example...\n";

// Create a buffered writer class
class BufferedWriter
{
    private $db;

    private $buffer = [];

    private $maxBufferSize;

    private $flushInterval;

    private $lastFlush;

    private $totalWritten = 0;

    public function __construct($db, $maxBufferSize = 100, $flushInterval = 5)
    {
        $this->db = $db;
        $this->maxBufferSize = $maxBufferSize;
        $this->flushInterval = $flushInterval;
        $this->lastFlush = time();
    }

    public function write(DataPoint $dataPoint)
    {
        $this->buffer[] = $dataPoint;

        // Flush if buffer is full or flush interval has passed
        if (count($this->buffer) >= $this->maxBufferSize || time() - $this->lastFlush >= $this->flushInterval) {
            return $this->flush();
        }

        return true;
    }

    public function flush()
    {
        if (empty($this->buffer)) {
            return true;
        }

        try {
            $success = $this->db->writeBatch($this->buffer);
            if ($success) {
                $this->totalWritten += count($this->buffer);
            }
            $this->buffer = [];
            $this->lastFlush = time();

            return $success;
        } catch (\Exception $e) {
            echo "Error in buffered flush: {$e->getMessage()}\n";

            return false;
        }
    }

    public function getTotalWritten()
    {
        return $this->totalWritten;
    }

    public function __destruct()
    {
        // Ensure all data is written when the object is destroyed
        $this->flush();
    }
}

// Create a buffered writer
$bufferedWriter = new BufferedWriter($db, 50, 2);

echo "Writing 200 data points using buffered writer (buffer size: 50, flush interval: 2s)...\n";

// Generate and write data points
$startTime = new DateTime;
$startTime->modify('-6 hours');

for ($i = 0; $i < 200; $i++) {
    $timestamp = clone $startTime;
    $timestamp->modify("+{$i} minute");

    $dataPoint = createDataPoint($driver, $timestamp, $i);
    $bufferedWriter->write($dataPoint);

    // Simulate some processing time
    usleep(10000); // 10ms

    // Show progress every 50 points
    if (($i + 1) % 50 === 0) {
        echo 'Processed '.($i + 1)." data points...\n";
    }
}

// Final flush to ensure all data is written
$bufferedWriter->flush();

echo 'Buffered writing complete. Total points written: '.$bufferedWriter->getTotalWritten()."\n";

// Step 5: Performance comparison
echo "\nStep 5: Performance comparison...\n";

// Number of data points for comparison
$comparisonPoints = 100;

// Generate data points
$dataPoints = [];
$startTime = new DateTime;
$startTime->modify('-3 hours');

for ($i = 0; $i < $comparisonPoints; $i++) {
    $timestamp = clone $startTime;
    $timestamp->modify("+{$i} minute");

    $dataPoint = createDataPoint($driver, $timestamp, $i);
    $dataPoints[] = $dataPoint;
}

// Method 1: Individual writes
echo "Method 1: Individual writes for {$comparisonPoints} data points...\n";
$individualStartTime = microtime(true);
$individualSuccess = 0;

for ($i = 0; $i < $comparisonPoints; $i++) {
    try {
        if ($db->write($dataPoints[$i])) {
            $individualSuccess++;
        }
    } catch (\Exception $e) {
        // Ignore errors for this test
    }
}

$individualEndTime = microtime(true);
$individualDuration = round(($individualEndTime - $individualStartTime) * 1000, 2);

echo "Individual writes: {$individualSuccess}/{$comparisonPoints} successful in {$individualDuration}ms.\n";

// Method 2: Batch write
echo "Method 2: Batch write for {$comparisonPoints} data points...\n";
$batchStartTime = microtime(true);
$batchSuccess = 0;

try {
    if ($db->writeBatch($dataPoints)) {
        $batchSuccess = $comparisonPoints;
    }
} catch (\Exception $e) {
    echo "Error in batch write: {$e->getMessage()}\n";
}

$batchEndTime = microtime(true);
$batchDuration = round(($batchEndTime - $batchStartTime) * 1000, 2);

echo "Batch write: {$batchSuccess}/{$comparisonPoints} successful in {$batchDuration}ms.\n";

// Method 3: Chunked batch writes
echo "Method 3: Chunked batch writes for {$comparisonPoints} data points (chunks of 20)...\n";
$chunkedStartTime = microtime(true);
$chunkedSuccess = 0;
$chunkSize = 20;
$chunks = ceil($comparisonPoints / $chunkSize);

for ($chunk = 0; $chunk < $chunks; $chunk++) {
    $chunkDataPoints = [];
    $chunkStart = $chunk * $chunkSize;
    $chunkEnd = min(($chunk + 1) * $chunkSize, $comparisonPoints);

    for ($i = $chunkStart; $i < $chunkEnd; $i++) {
        $chunkDataPoints[] = $dataPoints[$i];
    }

    try {
        if ($db->writeBatch($chunkDataPoints)) {
            $chunkedSuccess += count($chunkDataPoints);
        }
    } catch (\Exception $e) {
        // Ignore errors for this test
    }
}

$chunkedEndTime = microtime(true);
$chunkedDuration = round(($chunkedEndTime - $chunkedStartTime) * 1000, 2);

echo "Chunked batch writes: {$chunkedSuccess}/{$comparisonPoints} successful in {$chunkedDuration}ms.\n";

// Performance comparison
echo "\nPerformance Comparison:\n";
echo "Individual writes: {$individualDuration}ms ({$individualSuccess} points)\n";
echo "Batch write: {$batchDuration}ms ({$batchSuccess} points)\n";
echo "Chunked batch writes: {$chunkedDuration}ms ({$chunkedSuccess} points)\n";

if ($individualSuccess > 0 && $batchSuccess > 0) {
    $speedup = round($individualDuration / $batchDuration, 2);
    echo "Batch write is {$speedup}x faster than individual writes.\n";
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
            // Use the token from docker-compose.yml
            return new \TimeSeriesPhp\Drivers\InfluxDB\InfluxDBConfig([
                'url' => 'http://localhost:8086',
                'token' => 'my-token',
                'org' => 'my-org',
                'bucket' => 'example_bucket',
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
                'prefix' => 'example.',
            ]);

        case 'rrdtool':
            $rrdPath = __DIR__.'/rrd_files';
            if (! is_dir($rrdPath)) {
                mkdir($rrdPath, 0755, true);
            }

            return new \TimeSeriesPhp\Drivers\RRDtool\RRDtoolConfig([
                'path' => $rrdPath,
                'rrdtool_bin' => '/usr/bin/rrdtool',
                'default_step' => 60, // 1 minute for this example
            ]);

        default:
            throw new \InvalidArgumentException("Unsupported driver: {$driver}");
    }
}

/**
 * Helper function to create a data point appropriate for the specified driver
 */
function createDataPoint($driver, $timestamp, $index)
{
    $value = 50 + 30 * sin($index / 10); // Generate a sine wave pattern

    switch ($driver) {
        case 'influxdb':
            return new DataPoint(
                'batch_example',
                ['value' => $value],
                [
                    'host' => 'server'.($index % 5 + 1),
                    'region' => ($index % 2 === 0) ? 'us-west' : 'us-east',
                    'environment' => ($index % 3 === 0) ? 'production' : 'staging',
                ],
                $timestamp
            );

        case 'prometheus':
            return new DataPoint(
                'batch_example',
                ['value' => $value],
                [
                    'host' => 'server'.($index % 5 + 1),
                    'region' => ($index % 2 === 0) ? 'us-west' : 'us-east',
                    'environment' => ($index % 3 === 0) ? 'production' : 'staging',
                    'job' => 'batch_example', // Required for Prometheus
                ],
                $timestamp
            );

        case 'graphite':
            // For Graphite, we can use either dot notation or tags
            if ($index % 2 === 0) {
                // Using dot notation
                return new DataPoint(
                    'servers.server'.($index % 5 + 1).'.batch_example',
                    ['value' => $value],
                    [],
                    $timestamp
                );
            } else {
                // Using tags (for newer Graphite versions)
                return new DataPoint(
                    'batch_example',
                    ['value' => $value],
                    [
                        'host' => 'server'.($index % 5 + 1),
                        'region' => ($index % 2 === 0) ? 'us-west' : 'us-east',
                    ],
                    $timestamp
                );
            }

        case 'rrdtool':
            // For RRDtool, ensure the RRD file exists first
            static $rrdFilesCreated = [];
            $rrdFileName = 'batch_example_'.($index % 5 + 1).'.rrd';

            if (! isset($rrdFilesCreated[$rrdFileName])) {
                $rrdPath = __DIR__.'/rrd_files';
                $rrdFilePath = $rrdPath.'/'.$rrdFileName;

                if (! file_exists($rrdFilePath)) {
                    // This would normally be done separately, but for the example we'll do it here
                    global $db;
                    try {
                        $db->createRRDFile(
                            $rrdFileName,
                            60, // 1 minute step
                            [['name' => 'value', 'type' => 'GAUGE', 'min' => 0, 'max' => 100, 'heartbeat' => 120]],
                            [['cf' => 'AVERAGE', 'steps' => 1, 'rows' => 1440]] // 1 day of 1-minute data
                        );
                    } catch (\Exception $e) {
                        echo "Warning: Could not create RRD file {$rrdFileName}: {$e->getMessage()}\n";
                    }
                }

                $rrdFilesCreated[$rrdFileName] = true;
            }

            return new DataPoint(
                'batch_example_'.($index % 5 + 1), // Must match RRD file name without .rrd
                ['value' => $value],
                [],
                $timestamp
            );

        default:
            throw new \InvalidArgumentException("Unsupported driver: {$driver}");
    }
}

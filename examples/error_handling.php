<?php

require_once __DIR__.'/../vendor/autoload.php';

use TimeSeriesPhp\Core\Data\DataPoint;
use TimeSeriesPhp\Core\Factory\TSDBFactory;
use TimeSeriesPhp\Core\Query\Query;
use TimeSeriesPhp\Core\Query\RawQuery;
use TimeSeriesPhp\Drivers\Graphite\Config\GraphiteConfig;
use TimeSeriesPhp\Drivers\Graphite\GraphiteDriver;
use TimeSeriesPhp\Drivers\InfluxDB\Config\InfluxDBConfig;
use TimeSeriesPhp\Drivers\InfluxDB\InfluxDBDriver;
use TimeSeriesPhp\Drivers\Prometheus\Config\PrometheusConfig;
use TimeSeriesPhp\Drivers\Prometheus\PrometheusDriver;
use TimeSeriesPhp\Drivers\RRDtool\Config\RRDtoolConfig;
use TimeSeriesPhp\Drivers\RRDtool\RRDtoolDriver;
use TimeSeriesPhp\Exceptions\Config\ConfigurationException;
use TimeSeriesPhp\Exceptions\Connection\ConnectionException;
use TimeSeriesPhp\Exceptions\Query\QueryException;
use TimeSeriesPhp\Exceptions\TSDBException;
use TimeSeriesPhp\Exceptions\Write\WriteException;

/**
 * Error Handling Example
 *
 * This example demonstrates robust error handling strategies when working with
 * time series databases, including exception handling, retry mechanisms, and
 * circuit breakers.
 */
echo "TimeSeriesPhp Error Handling Example\n";
echo "===================================\n\n";

// Choose a driver to use for this example
// Options: 'influxdb', 'prometheus', 'graphite', 'rrdtool'
$driver = 'influxdb';

// Register the driver
switch ($driver) {
    case 'influxdb':
        TSDBFactory::registerDriver('influxdb', InfluxDBDriver::class);
        break;
    case 'prometheus':
        TSDBFactory::registerDriver('prometheus', PrometheusDriver::class);
        break;
    case 'graphite':
        TSDBFactory::registerDriver('graphite', GraphiteDriver::class);
        break;
    case 'rrdtool':
        TSDBFactory::registerDriver('rrdtool', RRDtoolDriver::class);
        break;
}

// Step 1: Basic exception handling
echo "Step 1: Basic exception handling...\n";

try {
    // Intentionally use an invalid configuration to trigger an exception
    $invalidConfig = createInvalidConfig($driver);

    // This should throw a ConfigurationException
    $db = TSDBFactory::create($driver, $invalidConfig);

    echo "This line should not be reached.\n";
} catch (ConfigurationException $e) {
    echo 'Caught ConfigurationException: '.$e->getMessage()."\n";
    echo 'Exception type: '.get_class($e)."\n";
} catch (TSDBException $e) {
    echo 'Caught TSDBException: '.$e->getMessage()."\n";
} catch (\Exception $e) {
    echo 'Caught generic Exception: '.$e->getMessage()."\n";
}

// Step 2: Hierarchical exception handling
echo "\nStep 2: Hierarchical exception handling...\n";

try {
    // Create a valid configuration
    $config = createConfig($driver);

    // Create database instance
    $db = TSDBFactory::create($driver, $config);
    echo "Successfully connected to {$driver}.\n";

    // Try to write a data point with an invalid measurement name
    $invalidDataPoint = new DataPoint(
        '', // Empty measurement name (invalid)
        ['value' => 42]
    );

    // This should throw a WriteException
    $db->write($invalidDataPoint);

    echo "This line should not be reached.\n";
} catch (ConnectionException $e) {
    // Handle connection-specific errors
    echo 'Caught ConnectionException: '.$e->getMessage()."\n";
} catch (WriteException $e) {
    // Handle write-specific errors
    echo 'Caught WriteException: '.$e->getMessage()."\n";
    echo 'Exception type: '.get_class($e)."\n";
} catch (TSDBException $e) {
    // Handle any other TimeSeriesPhp-specific errors
    echo 'Caught TSDBException: '.$e->getMessage()."\n";
} catch (\Exception $e) {
    // Handle any other unexpected errors
    echo 'Caught generic Exception: '.$e->getMessage()."\n";
}

// Step 3: Handling query errors
echo "\nStep 3: Handling query errors...\n";

try {
    // Create a valid configuration and connect
    if (! isset($db) || ! $db) {
        $config = createConfig($driver);
        $db = TSDBFactory::create($driver, $config);
    }

    // Create an invalid query (e.g., with a syntax error in the raw query)
    $invalidQuery = 'This is not a valid query';

    // This should throw a QueryException
    $result = $db->rawQuery(new RawQuery($invalidQuery));

    echo "This line should not be reached.\n";
} catch (QueryException $e) {
    echo 'Caught QueryException: '.$e->getMessage()."\n";
    echo 'Exception type: '.get_class($e)."\n";
} catch (TSDBException $e) {
    echo 'Caught TSDBException: '.$e->getMessage()."\n";
} catch (\Exception $e) {
    echo 'Caught generic Exception: '.$e->getMessage()."\n";
}

// Step 4: Implementing a retry mechanism
echo "\nStep 4: Implementing a retry mechanism...\n";

/**
 * Retry function that executes a callback with exponential backoff
 */
function retryWithExponentialBackoff(callable $callback, array $retryableExceptions, int $maxRetries = 3, int $initialDelay = 100)
{
    $attempt = 0;
    $delay = $initialDelay;

    while (true) {
        try {
            $attempt++;
            echo "Attempt {$attempt}...\n";

            // Execute the callback
            return $callback();
        } catch (\Exception $e) {
            // Check if we should retry this exception
            $shouldRetry = false;
            foreach ($retryableExceptions as $exceptionClass) {
                if ($e instanceof $exceptionClass) {
                    $shouldRetry = true;
                    break;
                }
            }

            // If we shouldn't retry or we've reached max retries, rethrow
            if (! $shouldRetry || $attempt >= $maxRetries) {
                throw $e;
            }

            // Log the error and wait before retrying
            echo "Error on attempt {$attempt}: ".$e->getMessage()."\n";
            echo 'Retrying in '.($delay / 1000)." seconds...\n";

            // Wait before retrying (convert milliseconds to microseconds)
            usleep($delay * 1000);

            // Exponential backoff
            $delay *= 2;
        }
    }
}

// Example of using the retry mechanism for writing data
try {
    // Create a valid configuration and connect if not already connected
    if (! isset($db) || ! $db) {
        $config = createConfig($driver);
        $db = TSDBFactory::create($driver, $config);
    }

    // Define which exceptions are retryable
    $retryableExceptions = [
        ConnectionException::class,
        WriteException::class,
    ];

    // Create a valid data point
    $dataPoint = new DataPoint(
        'error_handling_example',
        ['value' => 42],
        ['test' => 'retry_mechanism']
    );

    // Use the retry mechanism
    $result = retryWithExponentialBackoff(
        function () use ($db, $dataPoint) {
            // For demonstration, let's simulate a failure on the first attempt
            static $attempts = 0;
            $attempts++;

            if ($attempts === 1) {
                throw new ConnectionException('Simulated connection error for retry demonstration');
            }

            // This should succeed on the second attempt
            return $db->write($dataPoint);
        },
        $retryableExceptions,
        3,  // Max retries
        500 // Initial delay in milliseconds
    );

    echo 'Write operation '.($result ? 'succeeded' : 'failed')." after retries.\n";
} catch (\Exception $e) {
    echo 'Retry mechanism failed: '.$e->getMessage()."\n";
}

// Step 5: Implementing a circuit breaker
echo "\nStep 5: Implementing a circuit breaker...\n";

/**
 * Simple circuit breaker implementation
 */
class CircuitBreaker
{
    const STATE_CLOSED = 'CLOSED';       // Normal operation, requests go through

    const STATE_OPEN = 'OPEN';           // Circuit is open, requests fail fast

    const STATE_HALF_OPEN = 'HALF_OPEN'; // Testing if the service is back

    private $state = self::STATE_CLOSED;

    private $failureThreshold;

    private $failureCount = 0;

    private $resetTimeout;

    private $lastFailureTime = 0;

    public function __construct(int $failureThreshold = 3, int $resetTimeout = 30)
    {
        $this->failureThreshold = $failureThreshold;
        $this->resetTimeout = $resetTimeout;
    }

    public function execute(callable $callback)
    {
        $this->checkState();

        if ($this->state === self::STATE_OPEN) {
            throw new \RuntimeException('Circuit breaker is open - failing fast');
        }

        try {
            $result = $callback();

            // If we're in half-open state and the call succeeded, close the circuit
            if ($this->state === self::STATE_HALF_OPEN) {
                $this->state = self::STATE_CLOSED;
                $this->failureCount = 0;
                echo "Circuit is now CLOSED\n";
            }

            return $result;
        } catch (\Exception $e) {
            $this->recordFailure();
            throw $e;
        }
    }

    private function recordFailure()
    {
        $this->failureCount++;
        $this->lastFailureTime = time();

        if ($this->failureCount >= $this->failureThreshold) {
            $this->state = self::STATE_OPEN;
            echo "Circuit is now OPEN\n";
        }
    }

    private function checkState()
    {
        if ($this->state === self::STATE_OPEN) {
            $timeElapsed = time() - $this->lastFailureTime;

            if ($timeElapsed >= $this->resetTimeout) {
                $this->state = self::STATE_HALF_OPEN;
                echo "Circuit is now HALF_OPEN\n";
            }
        }
    }

    public function getState()
    {
        return $this->state;
    }

    public function reset()
    {
        $this->state = self::STATE_CLOSED;
        $this->failureCount = 0;
        echo "Circuit has been manually reset to CLOSED\n";
    }
}

// Example of using the circuit breaker
$circuitBreaker = new CircuitBreaker(3, 5); // 3 failures, 5 second timeout

// Create a valid configuration and connect if not already connected
if (! isset($db) || ! $db) {
    $config = createConfig($driver);
    $db = TSDBFactory::create($driver, $config);
}

// Run a series of operations with the circuit breaker
for ($i = 1; $i <= 10; $i++) {
    echo "\nOperation {$i}:\n";

    try {
        $result = $circuitBreaker->execute(function () use ($db, $i) {
            // Simulate failures for the first 4 operations
            if ($i <= 4) {
                throw new ConnectionException('Simulated failure for circuit breaker demonstration');
            }

            // This should succeed for operations 5-10
            $dataPoint = new DataPoint(
                'error_handling_example',
                ['value' => $i],
                ['test' => 'circuit_breaker']
            );

            return $db->write($dataPoint);
        });

        echo "Operation succeeded.\n";
    } catch (\RuntimeException $e) {
        // This is thrown when the circuit is open
        echo 'Circuit breaker prevented operation: '.$e->getMessage()."\n";
    } catch (\Exception $e) {
        echo 'Operation failed: '.$e->getMessage()."\n";
    }

    // Show the current state of the circuit breaker
    echo 'Circuit breaker state: '.$circuitBreaker->getState()."\n";

    // Wait a bit between operations
    sleep(1);
}

// Manually reset the circuit breaker
$circuitBreaker->reset();

// Step 6: Handling database-specific errors
echo "\nStep 6: Handling database-specific errors...\n";

// Each database driver may have specific error handling requirements
switch ($driver) {
    case 'influxdb':
        try {
            // Example: Try to query a non-existent bucket/database
            $result = $db->rawQuery(new RawQuery('from(bucket: "non_existent_bucket") |> range(start: -1h)'));
        } catch (QueryException $e) {
            echo 'InfluxDB-specific error: '.$e->getMessage()."\n";
        }
        break;

    case 'prometheus':
        try {
            // Example: Try to query with invalid PromQL syntax
            $result = $db->rawQuery(new RawQuery('invalid_metric{'));
        } catch (QueryException $e) {
            echo 'Prometheus-specific error: '.$e->getMessage()."\n";
        }
        break;

    case 'graphite':
        try {
            // Example: Try to query with invalid function
            $result = $db->rawQuery(new RawQuery('nonexistentFunction(servers.*.cpu)'));
        } catch (QueryException $e) {
            echo 'Graphite-specific error: '.$e->getMessage()."\n";
        }
        break;

    case 'rrdtool':
        try {
            // Example: Try to query a non-existent RRD file
            $result = $db->rawQuery(new RawQuery('fetch nonexistent.rrd AVERAGE'));
        } catch (QueryException $e) {
            echo 'RRDtool-specific error: '.$e->getMessage()."\n";
        }
        break;
}

// Step 7: Graceful degradation
echo "\nStep 7: Graceful degradation...\n";

/**
 * Example of a function that gracefully degrades when the primary operation fails
 */
function withGracefulDegradation(callable $primaryOperation, callable $fallbackOperation, array $fallbackExceptions)
{
    try {
        // Try the primary operation first
        return $primaryOperation();
    } catch (\Exception $e) {
        // Check if this exception should trigger the fallback
        $shouldFallback = false;
        foreach ($fallbackExceptions as $exceptionClass) {
            if ($e instanceof $exceptionClass) {
                $shouldFallback = true;
                break;
            }
        }

        if (! $shouldFallback) {
            // If it's not a fallback exception, rethrow
            throw $e;
        }

        // Log the error
        echo 'Primary operation failed: '.$e->getMessage()."\n";
        echo "Falling back to alternative operation...\n";

        // Execute the fallback operation
        return $fallbackOperation();
    }
}

// Example of using graceful degradation for querying
try {
    // Create a valid configuration and connect if not already connected
    if (! isset($db) || ! $db) {
        $config = createConfig($driver);
        $db = TSDBFactory::create($driver, $config);
    }

    // Define which exceptions should trigger the fallback
    $fallbackExceptions = [
        ConnectionException::class,
        QueryException::class,
    ];

    // Use the graceful degradation pattern
    $result = withGracefulDegradation(
        function () use ($db) {
            // Primary operation - complex query that might fail
            $query = new Query('error_handling_example');
            $query->select(['value'])
                ->where('test', '=', 'non_existent_tag')
                ->timeRange(new DateTime('-1 hour'), new DateTime)
                ->groupByTime('5m')
                ->avg('value', 'avg_value');

            // Simulate a failure
            throw new QueryException('Simulated query error for graceful degradation demonstration');

            return $db->query($query);
        },
        function () use ($db) {
            // Fallback operation - simpler query that's more likely to succeed
            $query = new Query('error_handling_example');
            $query->select(['value'])
                ->timeRange(new DateTime('-1 hour'), new DateTime);

            echo "Executing fallback query...\n";

            return $db->query($query);
        },
        $fallbackExceptions
    );

    echo "Query completed with graceful degradation.\n";
    echo 'Result contains '.count($result->getSeries())." data points.\n";
} catch (\Exception $e) {
    echo 'Both primary and fallback operations failed: '.$e->getMessage()."\n";
}

// Step 8: Logging and monitoring
echo "\nStep 8: Logging and monitoring...\n";

/**
 * Example of a logging wrapper for database operations
 */
class LoggingWrapper
{
    private $db;

    private $logger;

    public function __construct($db, callable $logger)
    {
        $this->db = $db;
        $this->logger = $logger;
    }

    public function write(DataPoint $dataPoint)
    {
        $startTime = microtime(true);
        $success = false;
        $error = null;

        try {
            $result = $this->db->write($dataPoint);
            $success = $result;

            return $result;
        } catch (\Exception $e) {
            $error = $e;
            throw $e;
        } finally {
            $endTime = microtime(true);
            $duration = round(($endTime - $startTime) * 1000, 2);

            // Log the operation
            call_user_func($this->logger, [
                'operation' => 'write',
                'measurement' => $dataPoint->getMeasurement(),
                'success' => $success,
                'duration_ms' => $duration,
                'error' => $error ? $error->getMessage() : null,
                'timestamp' => date('Y-m-d H:i:s'),
            ]);
        }
    }

    public function query(Query $query)
    {
        $startTime = microtime(true);
        $success = false;
        $error = null;

        try {
            $result = $this->db->query($query);
            $success = true;

            return $result;
        } catch (\Exception $e) {
            $error = $e;
            throw $e;
        } finally {
            $endTime = microtime(true);
            $duration = round(($endTime - $startTime) * 1000, 2);

            // Log the operation
            call_user_func($this->logger, [
                'operation' => 'query',
                'measurement' => $query->getMeasurement(),
                'success' => $success,
                'duration_ms' => $duration,
                'error' => $error ? $error->getMessage() : null,
                'timestamp' => date('Y-m-d H:i:s'),
            ]);
        }
    }

    // Proxy other methods to the underlying database
    public function __call($method, $args)
    {
        return call_user_func_array([$this->db, $method], $args);
    }
}

// Create a simple logger function
$logger = function ($data) {
    echo '[LOG] '.json_encode($data)."\n";
};

// Create a logging wrapper around the database
$loggingDb = new LoggingWrapper($db, $logger);

// Example of using the logging wrapper
try {
    // Write a data point
    $dataPoint = new DataPoint(
        'error_handling_example',
        ['value' => 100],
        ['test' => 'logging']
    );

    $loggingDb->write($dataPoint);

    // Execute a query
    $query = new Query('error_handling_example');
    $query->select(['value'])
        ->where('test', '=', 'logging')
        ->timeRange(new DateTime('-1 hour'), new DateTime);

    $result = $loggingDb->query($query);

    // Try an operation that will fail
    $invalidQuery = new Query('');
    $loggingDb->query($invalidQuery);
} catch (\Exception $e) {
    echo 'Expected error: '.$e->getMessage()."\n";
}

// Close the connection
if (isset($db) && $db) {
    $db->close();
}
echo "\nConnection closed.\n";

echo "\nExample completed successfully!\n";

/**
 * Helper function to create an invalid configuration for testing exceptions
 */
function createInvalidConfig($driver)
{
    switch ($driver) {
        case 'influxdb':
            return new InfluxDBConfig([
                'url' => 'http://non-existent-host:8086',
                'token' => 'invalid-token',
                'org' => '',  // Invalid empty org
                'bucket' => 'example_bucket',
            ]);

        case 'prometheus':
            return new PrometheusConfig([
                'url' => 'http://non-existent-host:9090',
            ]);

        case 'graphite':
            return new GraphiteConfig([
                'host' => 'non-existent-host',
                'port' => 2003,
                'protocol' => 'invalid-protocol', // Invalid protocol
            ]);

        case 'rrdtool':
            return new RRDtoolConfig([
                'path' => '/non/existent/path',
                'rrdtool_bin' => '/non/existent/rrdtool',
            ]);

        default:
            throw new \InvalidArgumentException("Unsupported driver: {$driver}");
    }
}

/**
 * Helper function to create a valid configuration
 */
function createConfig($driver)
{
    switch ($driver) {
        case 'influxdb':
            // Use the token from docker-compose.yml
            return new InfluxDBConfig([
                'url' => 'http://localhost:8086',
                'token' => 'my-token',
                'org' => 'my-org',
                'bucket' => 'example_bucket',
            ]);

        case 'prometheus':
            return new PrometheusConfig([
                'url' => 'http://localhost:9090',
            ]);

        case 'graphite':
            return new GraphiteConfig([
                'host' => 'localhost',
                'port' => 2003,
                'protocol' => 'tcp',
            ]);

        case 'rrdtool':
            $rrdPath = __DIR__.'/rrd_files';
            if (! is_dir($rrdPath)) {
                mkdir($rrdPath, 0755, true);
            }

            return new RRDtoolConfig([
                'path' => $rrdPath,
                'rrdtool_bin' => '/usr/bin/rrdtool',
            ]);

        default:
            throw new \InvalidArgumentException("Unsupported driver: {$driver}");
    }
}

<?php

namespace TimeSeriesPhp\Tests\Benchmarks;

use DateTime;
use PHPUnit\Framework\TestCase;
use TimeSeriesPhp\Core\Data\DataPoint;
use TimeSeriesPhp\Core\Driver\AbstractTimeSeriesDB;
use TimeSeriesPhp\Core\Query\Query;

/**
 * Abstract benchmark class for driver performance testing.
 *
 * @group benchmark
 */
abstract class AbstractDriverBenchmark extends TestCase
{
    protected AbstractTimeSeriesDB $driver;

    protected int $iterations = 100;

    protected int $batchSize = 1000;

    protected string $measurement = 'benchmark_test';

    /**
     * Set up the driver for benchmarking.
     * This method should be implemented by each driver-specific benchmark class.
     */
    abstract protected function setUpDriver(): void;

    /**
     * Clean up after benchmarking.
     * This method should be implemented by each driver-specific benchmark class.
     */
    abstract protected function tearDownDriver(): void;

    protected function setUp(): void
    {
        $this->setUpDriver();
    }

    protected function tearDown(): void
    {
        $this->tearDownDriver();
    }

    /**
     * Benchmark single write performance.
     */
    public function test_benchmark_single_write(): void
    {
        if (! $this->driver->isConnected()) {
            $this->markTestSkipped('Driver is not connected');
        }

        $start = microtime(true);

        for ($i = 0; $i < $this->iterations; $i++) {
            $dataPoint = new DataPoint(
                $this->measurement,
                ['value' => rand(1, 100) / 10],
                ['host' => 'benchmark-host', 'iteration' => (string) $i],
                new DateTime
            );

            $this->driver->write($dataPoint);
        }

        $end = microtime(true);
        $duration = $end - $start;
        $pointsPerSecond = $this->iterations / $duration;

        $this->addToAssertionCount(1); // Count this as a passed assertion

        echo sprintf(
            "Single write benchmark: %d points in %.4f seconds (%.2f points/sec)\n",
            $this->iterations,
            $duration,
            $pointsPerSecond
        );
    }

    /**
     * Benchmark batch write performance.
     */
    public function test_benchmark_batch_write(): void
    {
        if (! $this->driver->isConnected()) {
            $this->markTestSkipped('Driver is not connected');
        }

        $dataPoints = [];
        for ($i = 0; $i < $this->batchSize; $i++) {
            $dataPoints[] = new DataPoint(
                $this->measurement,
                ['value' => rand(1, 100) / 10],
                ['host' => 'benchmark-host', 'iteration' => (string) $i],
                new DateTime
            );
        }

        $start = microtime(true);

        $this->driver->writeBatch($dataPoints);

        $end = microtime(true);
        $duration = $end - $start;
        $pointsPerSecond = $this->batchSize / $duration;

        $this->addToAssertionCount(1); // Count this as a passed assertion

        echo sprintf(
            "Batch write benchmark: %d points in %.4f seconds (%.2f points/sec)\n",
            $this->batchSize,
            $duration,
            $pointsPerSecond
        );
    }

    /**
     * Benchmark query performance.
     */
    public function test_benchmark_query(): void
    {
        if (! $this->driver->isConnected()) {
            $this->markTestSkipped('Driver is not connected');
        }

        // First write some data to query
        $dataPoints = [];
        for ($i = 0; $i < 100; $i++) {
            $dataPoints[] = new DataPoint(
                $this->measurement,
                ['value' => rand(1, 100) / 10],
                ['host' => 'benchmark-query-host'],
                (new DateTime)->modify("-{$i} seconds")
            );
        }

        $this->driver->writeBatch($dataPoints);

        // Wait a moment for data to be available
        sleep(1);

        $start = microtime(true);

        for ($i = 0; $i < 10; $i++) {
            $query = new Query($this->measurement);
            $query->select(['value'])
                ->where('host', '=', 'benchmark-query-host')
                ->timeRange(
                    (new DateTime)->modify('-2 minutes'),
                    new DateTime
                );

            $this->driver->query($query);
        }

        $end = microtime(true);
        $duration = $end - $start;
        $queriesPerSecond = 10 / $duration;

        $this->addToAssertionCount(1); // Count this as a passed assertion

        echo sprintf(
            "Query benchmark: 10 queries in %.4f seconds (%.2f queries/sec)\n",
            $duration,
            $queriesPerSecond
        );
    }
}

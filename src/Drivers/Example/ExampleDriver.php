<?php

declare(strict_types=1);

namespace TimeSeriesPhp\Drivers\Example;

use DateTime;
use TimeSeriesPhp\Contracts\Driver\ConfigurableInterface;
use TimeSeriesPhp\Contracts\Driver\TimeSeriesInterface;
use TimeSeriesPhp\Contracts\Query\RawQueryInterface;
use TimeSeriesPhp\Core\Attributes\Driver;
use TimeSeriesPhp\Core\Data\DataPoint;
use TimeSeriesPhp\Core\Data\QueryResult;
use TimeSeriesPhp\Core\Query\Query;
use TimeSeriesPhp\Exceptions\Driver\ConnectionException;
use TimeSeriesPhp\Exceptions\Driver\DatabaseException;
use TimeSeriesPhp\Exceptions\Driver\WriteException;
use TimeSeriesPhp\Exceptions\Query\QueryException;
use TimeSeriesPhp\Exceptions\Query\RawQueryException;
use TimeSeriesPhp\Exceptions\TSDBException;

/**
 * Example driver implementation
 */
#[Driver(name: 'example', configClass: ExampleDriverConfiguration::class)]
class ExampleDriver implements TimeSeriesInterface, ConfigurableInterface
{
    /**
     * @var array<string, mixed> The driver configuration
     */
    private array $config = [];

    /**
     * @var bool Whether the driver is connected
     */
    private bool $connected = false;

    /**
     * Configure the driver with the given configuration
     *
     * @param array<string, mixed> $config Configuration for the driver
     * @return void
     */
    public function configure(array $config): void
    {
        // Process the configuration using the driver's configuration class
        $configProcessor = new ExampleDriverConfiguration();
        $this->config = $configProcessor->processConfiguration($config);
    }

    /**
     * Connect to the time series database
     *
     * @return bool True if connection was successful
     *
     * @throws ConnectionException If connection fails
     */
    public function connect(): bool
    {
        try {
            // In a real driver, this would establish a connection to the database
            // For this example, we'll just set the connected flag to true
            $this->connected = true;

            return true;
        } catch (\Exception $e) {
            throw new ConnectionException('Failed to connect to Example database: ' . $e->getMessage(), previous: $e);
        }
    }

    /**
     * Check if connected to the database
     *
     * @return bool True if connected
     */
    public function isConnected(): bool
    {
        return $this->connected;
    }

    /**
     * Write a single data point to the database
     *
     * @param DataPoint $dataPoint The data point to write
     * @return bool True if write was successful
     *
     * @throws WriteException If write fails
     */
    public function write(DataPoint $dataPoint): bool
    {
        if (!$this->isConnected()) {
            throw new WriteException('Not connected to the database');
        }

        try {
            // In a real driver, this would write the data point to the database
            // For this example, we'll just return true
            return true;
        } catch (\Exception $e) {
            throw new WriteException('Failed to write data point: ' . $e->getMessage(), previous: $e);
        }
    }

    /**
     * Write multiple data points to the database in a single operation
     *
     * @param DataPoint[] $dataPoints Array of data points to write
     * @return bool True if write was successful
     *
     * @throws WriteException If write fails
     */
    public function writeBatch(array $dataPoints): bool
    {
        if (!$this->isConnected()) {
            throw new WriteException('Not connected to the database');
        }

        try {
            // In a real driver, this would write the data points to the database
            // For this example, we'll just return true
            return true;
        } catch (\Exception $e) {
            throw new WriteException('Failed to write data points: ' . $e->getMessage(), previous: $e);
        }
    }

    /**
     * Execute a query using the Query builder
     *
     * @param Query $query The query to execute
     * @return QueryResult The query result
     *
     * @throws QueryException If query fails
     */
    public function query(Query $query): QueryResult
    {
        if (!$this->isConnected()) {
            throw new QueryException('Not connected to the database');
        }

        try {
            // In a real driver, this would execute the query and return the result
            // For this example, we'll just return an empty result
            return new QueryResult([]);
        } catch (\Exception $e) {
            throw new QueryException('Failed to execute query: ' . $e->getMessage(), previous: $e);
        }
    }

    /**
     * Execute a raw query
     *
     * @param RawQueryInterface $query The raw query to execute
     * @return QueryResult The query result
     *
     * @throws RawQueryException If query fails
     */
    public function rawQuery(RawQueryInterface $query): QueryResult
    {
        if (!$this->isConnected()) {
            throw new RawQueryException('Not connected to the database');
        }

        try {
            // In a real driver, this would execute the raw query and return the result
            // For this example, we'll just return an empty result
            return new QueryResult([]);
        } catch (\Exception $e) {
            throw new RawQueryException('Failed to execute raw query: ' . $e->getMessage(), previous: $e);
        }
    }

    /**
     * Create a new database
     *
     * @param string $database Name of the database to create
     * @return bool True if database was created successfully
     *
     * @throws DatabaseException If database creation fails
     */
    public function createDatabase(string $database): bool
    {
        if (!$this->isConnected()) {
            throw new DatabaseException('Not connected to the database');
        }

        try {
            // In a real driver, this would create a new database
            // For this example, we'll just return true
            return true;
        } catch (\Exception $e) {
            throw new DatabaseException('Failed to create database: ' . $e->getMessage(), previous: $e);
        }
    }

    /**
     * Delete a database
     *
     * @param string $database Name of the database to delete
     * @return bool True if database was deleted successfully
     *
     * @throws DatabaseException If database deletion fails
     */
    public function deleteDatabase(string $database): bool
    {
        if (!$this->isConnected()) {
            throw new DatabaseException('Not connected to the database');
        }

        try {
            // In a real driver, this would delete the database
            // For this example, we'll just return true
            return true;
        } catch (\Exception $e) {
            throw new DatabaseException('Failed to delete database: ' . $e->getMessage(), previous: $e);
        }
    }

    /**
     * Get a list of all databases
     *
     * @return string[] Array of database names
     *
     * @throws DatabaseException If listing databases fails
     */
    public function getDatabases(): array
    {
        if (!$this->isConnected()) {
            throw new DatabaseException('Not connected to the database');
        }

        try {
            // In a real driver, this would return a list of databases
            // For this example, we'll just return an empty array
            return [];
        } catch (\Exception $e) {
            throw new DatabaseException('Failed to get databases: ' . $e->getMessage(), previous: $e);
        }
    }

    /**
     * Delete a measurement (time series)
     *
     * @param string $measurement Name of the measurement to delete
     * @param DateTime|null $start Optional start time for deletion range
     * @param DateTime|null $stop Optional end time for deletion range
     * @return bool True if measurement was deleted successfully
     *
     * @throws DatabaseException If measurement deletion fails
     */
    public function deleteMeasurement(string $measurement, ?DateTime $start = null, ?DateTime $stop = null): bool
    {
        if (!$this->isConnected()) {
            throw new DatabaseException('Not connected to the database');
        }

        try {
            // In a real driver, this would delete the measurement
            // For this example, we'll just return true
            return true;
        } catch (\Exception $e) {
            throw new DatabaseException('Failed to delete measurement: ' . $e->getMessage(), previous: $e);
        }
    }

    /**
     * Close the connection to the database
     */
    public function close(): void
    {
        // In a real driver, this would close the connection to the database
        // For this example, we'll just set the connected flag to false
        $this->connected = false;
    }
}

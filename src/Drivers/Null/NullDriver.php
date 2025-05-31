<?php

declare(strict_types=1);

namespace TimeSeriesPhp\Drivers\Null;

use DateTime;
use TimeSeriesPhp\Contracts\Driver\ConfigurableInterface;
use TimeSeriesPhp\Contracts\Query\RawQueryInterface;
use TimeSeriesPhp\Core\Attributes\Driver;
use TimeSeriesPhp\Core\Data\DataPoint;
use TimeSeriesPhp\Core\Data\QueryResult;
use TimeSeriesPhp\Core\Driver\AbstractTimeSeriesDB;

/**
 * Null driver implementation that does nothing
 * Useful for testing or when you need a placeholder driver
 */
#[Driver(name: 'null', queryBuilderClass: NullQueryBuilder::class, configClass: NullConfig::class)]
class NullDriver extends AbstractTimeSeriesDB implements ConfigurableInterface
{
    /**
     * @var array{debug: bool} The driver configuration
     */
    private array $config = ['debug' => false];

    /**
     * @var bool Whether the driver is connected
     */
    private bool $connected = false;

    /**
     * Configure the driver with the given configuration
     *
     * @param  array<string, mixed>  $config  Configuration for the driver
     */
    public function configure(array $config): void
    {
        // Process the configuration using the driver's configuration class

        /** @var array{debug: bool} $processedConfig */
        $processedConfig = (new NullConfig)->processConfiguration($config);

        $this->config = $processedConfig;
    }

    /**
     * Implementation of connecting to the database
     *
     * @return bool True if connection was successful
     */
    protected function doConnect(): bool
    {
        $this->connected = true;

        return true;
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
     * Implementation of writing a single data point to the database
     *
     * @param  DataPoint  $dataPoint  The data point to write
     * @return bool True if write was successful
     */
    protected function doWrite(DataPoint $dataPoint): bool
    {
        // Null implementation does nothing but returns success
        return true;
    }

    /**
     * Execute a raw query
     *
     * @param  RawQueryInterface  $query  The raw query to execute
     * @return QueryResult The query result
     */
    public function rawQuery(RawQueryInterface $query): QueryResult
    {
        // Null implementation returns an empty result
        return new QueryResult([]);
    }

    /**
     * Create a new database
     *
     * @param  string  $database  Name of the database to create
     * @return bool True if database was created successfully
     */
    public function createDatabase(string $database): bool
    {
        // Null implementation does nothing but returns success
        return true;
    }

    /**
     * Get a list of all databases
     *
     * @return string[] Array of database names
     */
    public function getDatabases(): array
    {
        // Null implementation returns an empty array
        return [];
    }

    /**
     * Delete a database
     *
     * @param  string  $database  Name of the database to delete
     * @return bool True if database was deleted successfully
     */
    public function deleteDatabase(string $database): bool
    {
        // Null implementation does nothing but returns success
        return true;
    }

    /**
     * Delete a measurement (time series)
     *
     * @param  string  $measurement  Name of the measurement to delete
     * @param  DateTime|null  $start  Optional start time for deletion range
     * @param  DateTime|null  $stop  Optional end time for deletion range
     * @return bool True if measurement was deleted successfully
     */
    public function deleteMeasurement(string $measurement, ?DateTime $start = null, ?DateTime $stop = null): bool
    {
        // Null implementation does nothing but returns success
        return true;
    }

    /**
     * Close the connection to the database
     */
    public function close(): void
    {
        $this->connected = false;
    }

    /**
     * Get the driver configuration
     *
     * @return array<string, mixed> The driver configuration
     */
    public function getConfig(): array
    {
        return $this->config;
    }
}

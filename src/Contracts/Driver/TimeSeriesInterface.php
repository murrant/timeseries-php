<?php

namespace TimeSeriesPhp\Contracts\Driver;

use DateTime;
use TimeSeriesPhp\Contracts\Config\ConfigInterface;
use TimeSeriesPhp\Contracts\Query\RawQueryInterface;
use TimeSeriesPhp\Core\Data\DataPoint;
use TimeSeriesPhp\Core\Data\QueryResult;
use TimeSeriesPhp\Core\Query\Query;
use TimeSeriesPhp\Exceptions\Driver\ConnectionException;
use TimeSeriesPhp\Exceptions\Driver\DatabaseException;
use TimeSeriesPhp\Exceptions\Driver\WriteException;
use TimeSeriesPhp\Exceptions\Query\QueryException;
use TimeSeriesPhp\Exceptions\Query\RawQueryException;

interface TimeSeriesInterface
{
    /**
     * Connect to the time series database
     *
     * @param  ConfigInterface  $config  Configuration for the connection
     * @return bool True if connection was successful
     *
     * @throws ConnectionException If connection fails
     */
    public function connect(ConfigInterface $config): bool;

    /**
     * Check if connected to the database
     *
     * @return bool True if connected
     */
    public function isConnected(): bool;

    /**
     * Write a single data point to the database
     *
     * @param  DataPoint  $dataPoint  The data point to write
     * @return bool True if write was successful
     *
     * @throws WriteException If write fails
     */
    public function write(DataPoint $dataPoint): bool;

    /**
     * Write multiple data points to the database in a single operation
     *
     * @param  DataPoint[]  $dataPoints  Array of data points to write
     * @return bool True if write was successful
     *
     * @throws WriteException If write fails
     */
    public function writeBatch(array $dataPoints): bool;

    /**
     * Execute a query using the Query builder
     *
     * @param  Query  $query  The query to execute
     * @return QueryResult The query result
     *
     * @throws QueryException If query fails
     */
    public function query(Query $query): QueryResult;

    /**
     * Execute a raw query
     *
     * @param  RawQueryInterface  $query  The raw query to execute
     * @return QueryResult The query result
     *
     * @throws RawQueryException If query fails
     */
    public function rawQuery(RawQueryInterface $query): QueryResult;

    /**
     * Create a new database
     *
     * @param  string  $database  Name of the database to create
     * @return bool True if database was created successfully
     *
     * @throws DatabaseException If database creation fails
     */
    public function createDatabase(string $database): bool;

    /**
     * Delete a database
     *
     * @param  string  $database  Name of the database to delete
     * @return bool True if database was deleted successfully
     *
     * @throws DatabaseException If database deletion fails
     */
    public function deleteDatabase(string $database): bool;

    /**
     * Get a list of all databases
     *
     * @return string[] Array of database names
     *
     * @throws DatabaseException If listing databases fails
     */
    public function getDatabases(): array;

    /**
     * Delete a measurement (time series)
     *
     * @param  string  $measurement  Name of the measurement to delete
     * @param  DateTime|null  $start  Optional start time for deletion range
     * @param  DateTime|null  $stop  Optional end time for deletion range
     * @return bool True if measurement was deleted successfully
     *
     * @throws DatabaseException If measurement deletion fails
     */
    public function deleteMeasurement(string $measurement, ?DateTime $start = null, ?DateTime $stop = null): bool;

    /**
     * Close the connection to the database
     */
    public function close(): void;
}

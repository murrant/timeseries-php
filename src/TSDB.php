<?php

namespace TimeSeriesPhp;

use DateTime;
use TimeSeriesPhp\Contracts\Config\ConfigInterface;
use TimeSeriesPhp\Contracts\Driver\TimeSeriesInterface;
use TimeSeriesPhp\Core\Data\DataPoint;
use TimeSeriesPhp\Core\Data\QueryResult;
use TimeSeriesPhp\Core\Factory\TSDBFactory;
use TimeSeriesPhp\Core\Query\Query;
use TimeSeriesPhp\Exceptions\Driver\DriverException;
use TimeSeriesPhp\Exceptions\Driver\WriteException;
use TimeSeriesPhp\Exceptions\Query\QueryException;

/**
 * Simplified entry point for TimeSeriesPhp
 *
 * This class provides a simpler API for common operations with time series databases.
 * It's a wrapper around the TSDBFactory and TimeSeriesInterface that reduces boilerplate
 * for common operations.
 */
class TSDB
{
    protected TimeSeriesInterface $driver;

    /**
     * Create a new TimeSeries instance
     *
     * @param  string  $driver  The name of the driver to use
     * @param  ConfigInterface|null  $config  The configuration for the driver
     * @param  bool  $autoConnect  Whether to automatically connect to the database
     *
     * @throws DriverException If the driver is not registered or doesn't implement TimeSeriesInterface
     */
    public function __construct(string $driver, ?ConfigInterface $config = null, bool $autoConnect = true)
    {
        $this->driver = TSDBFactory::create($driver, $config, $autoConnect);
    }

    public static function start(string $driver, ?ConfigInterface $config = null, bool $autoConnect = true): self
    {
        return new self($driver, $config, $autoConnect);
    }

    /**
     * Get the underlying driver instance
     *
     * @return TimeSeriesInterface The driver instance
     */
    public function getDriver(): TimeSeriesInterface
    {
        return $this->driver;
    }

    /**
     * Write a data point to the database
     *
     * This is a simplified version of the write method that creates a DataPoint internally.
     *
     * @param  string  $measurement  The measurement name
     * @param  array<string, ?scalar>  $fields  The fields to write
     * @param  array<string, string>  $tags  The tags to associate with the data point
     * @param  DateTime|null  $timestamp  The timestamp for the data point (defaults to now)
     * @return bool True if the write was successful
     *
     * @throws WriteException If the write fails
     */
    public function write(string $measurement, array $fields, array $tags = [], ?DateTime $timestamp = null): bool
    {
        $dataPoint = new DataPoint($measurement, $fields, $tags, $timestamp);

        return $this->driver->write($dataPoint);
    }

    /**
     * Write multiple data points to the database
     *
     * @param  array<DataPoint>  $dataPoints  The data points to write
     * @return bool True if all data points were written successfully
     *
     * @throws WriteException If the write fails
     */
    public function writeBatch(array $dataPoints): bool
    {
        return $this->driver->writeBatch($dataPoints);
    }

    /**
     * Create and write a data point in one call
     *
     * @param  string  $measurement  The measurement name
     * @param  array<string, ?scalar>  $fields  The fields to write
     * @param  array<string, string>  $tags  The tags to associate with the data point
     * @param  DateTime|null  $timestamp  The timestamp for the data point (defaults to now)
     * @return bool True if the write was successful
     *
     * @throws WriteException If the write fails
     */
    public function writePoint(string $measurement, array $fields, array $tags = [], ?DateTime $timestamp = null): bool
    {
        return $this->write($measurement, $fields, $tags, $timestamp);
    }

    /**
     * Execute a query
     *
     * @param  Query  $query  The query to execute
     * @return QueryResult The query result
     *
     * @throws QueryException If the query fails
     */
    public function query(Query $query): QueryResult
    {
        return $this->driver->query($query);
    }

    /**
     * Get the last value for a field in a measurement
     *
     * @param  string  $measurement  The measurement name
     * @param  string  $field  The field to query
     * @param  array<string, string>  $tags  The tags to filter by
     * @return QueryResult The query result
     *
     * @throws QueryException If the query fails
     */
    public function queryLast(string $measurement, string $field, array $tags = []): QueryResult
    {
        $query = new Query($measurement);
        $query->select([$field]);

        // Add tag filters
        foreach ($tags as $key => $value) {
            $query->where($key, '=', $value);
        }

        // Order by time descending and limit to 1 to get the last value
        $query->orderByTime('DESC')->limit(1);

        return $this->driver->query($query);
    }

    /**
     * Get the first value for a field in a measurement
     *
     * @param  string  $measurement  The measurement name
     * @param  string  $field  The field to query
     * @param  array<string, string>  $tags  The tags to filter by
     * @return QueryResult The query result
     *
     * @throws QueryException If the query fails
     */
    public function queryFirst(string $measurement, string $field, array $tags = []): QueryResult
    {
        $query = new Query($measurement);
        $query->select([$field]);

        // Add tag filters
        foreach ($tags as $key => $value) {
            $query->where($key, '=', $value);
        }

        // Order by time ascending and limit to 1 to get the first value
        $query->orderByTime('ASC')->limit(1);

        return $this->driver->query($query);
    }

    /**
     * Get the average value for a field in a measurement over a time range
     *
     * @param  string  $measurement  The measurement name
     * @param  string  $field  The field to query
     * @param  DateTime  $start  The start time
     * @param  DateTime  $end  The end time
     * @param  array<string, string>  $tags  The tags to filter by
     * @return QueryResult The query result
     *
     * @throws QueryException If the query fails
     */
    public function queryAvg(string $measurement, string $field, DateTime $start, DateTime $end, array $tags = []): QueryResult
    {
        $query = new Query($measurement);
        $query->select([$field])
            ->timeRange($start, $end)
            ->avg($field, 'avg_'.$field);

        // Add tag filters
        foreach ($tags as $key => $value) {
            $query->where($key, '=', $value);
        }

        return $this->driver->query($query);
    }

    /**
     * Get the sum of values for a field in a measurement over a time range
     *
     * @param  string  $measurement  The measurement name
     * @param  string  $field  The field to query
     * @param  DateTime  $start  The start time
     * @param  DateTime  $end  The end time
     * @param  array<string, string>  $tags  The tags to filter by
     * @return QueryResult The query result
     *
     * @throws QueryException If the query fails
     */
    public function querySum(string $measurement, string $field, DateTime $start, DateTime $end, array $tags = []): QueryResult
    {
        $query = new Query($measurement);
        $query->select([$field])
            ->timeRange($start, $end)
            ->sum($field, 'sum_'.$field);

        // Add tag filters
        foreach ($tags as $key => $value) {
            $query->where($key, '=', $value);
        }

        return $this->driver->query($query);
    }

    /**
     * Get the count of values for a field in a measurement over a time range
     *
     * @param  string  $measurement  The measurement name
     * @param  string  $field  The field to query
     * @param  DateTime  $start  The start time
     * @param  DateTime  $end  The end time
     * @param  array<string, string>  $tags  The tags to filter by
     * @return QueryResult The query result
     *
     * @throws QueryException If the query fails
     */
    public function queryCount(string $measurement, string $field, DateTime $start, DateTime $end, array $tags = []): QueryResult
    {
        $query = new Query($measurement);
        $query->select([$field])
            ->timeRange($start, $end)
            ->count($field, 'count_'.$field);

        // Add tag filters
        foreach ($tags as $key => $value) {
            $query->where($key, '=', $value);
        }

        return $this->driver->query($query);
    }

    /**
     * Get the minimum value for a field in a measurement over a time range
     *
     * @param  string  $measurement  The measurement name
     * @param  string  $field  The field to query
     * @param  DateTime  $start  The start time
     * @param  DateTime  $end  The end time
     * @param  array<string, string>  $tags  The tags to filter by
     * @return QueryResult The query result
     *
     * @throws QueryException If the query fails
     */
    public function queryMin(string $measurement, string $field, DateTime $start, DateTime $end, array $tags = []): QueryResult
    {
        $query = new Query($measurement);
        $query->select([$field])
            ->timeRange($start, $end)
            ->min($field, 'min_'.$field);

        // Add tag filters
        foreach ($tags as $key => $value) {
            $query->where($key, '=', $value);
        }

        return $this->driver->query($query);
    }

    /**
     * Get the maximum value for a field in a measurement over a time range
     *
     * @param  string  $measurement  The measurement name
     * @param  string  $field  The field to query
     * @param  DateTime  $start  The start time
     * @param  DateTime  $end  The end time
     * @param  array<string, string>  $tags  The tags to filter by
     * @return QueryResult The query result
     *
     * @throws QueryException If the query fails
     */
    public function queryMax(string $measurement, string $field, DateTime $start, DateTime $end, array $tags = []): QueryResult
    {
        $query = new Query($measurement);
        $query->select([$field])
            ->timeRange($start, $end)
            ->max($field, 'max_'.$field);

        // Add tag filters
        foreach ($tags as $key => $value) {
            $query->where($key, '=', $value);
        }

        return $this->driver->query($query);
    }

    /**
     * Delete a measurement
     *
     * @param  string  $measurement  The measurement name
     * @param  DateTime|null  $start  The start time for deletion range
     * @param  DateTime|null  $stop  The end time for deletion range
     * @return bool True if the measurement was deleted successfully
     */
    public function deleteMeasurement(string $measurement, ?DateTime $start = null, ?DateTime $stop = null): bool
    {
        return $this->driver->deleteMeasurement($measurement, $start, $stop);
    }

    /**
     * Close the connection to the database
     */
    public function close(): void
    {
        $this->driver->close();
    }
}

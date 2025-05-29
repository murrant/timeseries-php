<?php

namespace TimeSeriesPhp\Support;

use DateTime;
use TimeSeriesPhp\Core\DataPoint;
use TimeSeriesPhp\Core\Query;
use TimeSeriesPhp\Core\QueryResult;
use TimeSeriesPhp\Core\TimeSeriesInterface;
use TimeSeriesPhp\Exceptions\DatabaseException;
use TimeSeriesPhp\Exceptions\WriteException;
use TimeSeriesPhp\Support\Config\ConfigInterface;
use TimeSeriesPhp\Support\Logs\Logger;
use TimeSeriesPhp\Support\Query\QueryBuilderInterface;

abstract class AbstractTimeSeriesDB implements TimeSeriesInterface
{
    protected ConfigInterface $config;

    protected bool $connected = false;

    protected QueryBuilderInterface $queryBuilder;

    public function isConnected(): bool
    {
        return $this->connected;
    }

    /**
     * Get the driver name from the class name
     */
    protected function getDriverName(): string
    {
        return basename(str_replace('\\', '/', get_class($this)));
    }

    public function connect(ConfigInterface $config): bool
    {
        $this->config = $config;

        Logger::info('Connecting to time series database', [
            'driver' => $this->getDriverName(),
        ]);

        return $this->doConnect();
    }

    abstract protected function doConnect(): bool;

    public function query(Query $query): QueryResult
    {
        Logger::debug('Executing query', [
            'driver' => $this->getDriverName(),
            'measurement' => $query->getMeasurement(),
            'fields' => $query->getFields(),
            'conditions' => $query->getConditions(),
        ]);

        $rawQuery = $this->queryBuilder->build($query);
        $result = $this->rawQuery($rawQuery);

        Logger::debug('Query executed', [
            'driver' => $this->getDriverName(),
            'points_count' => $result->count(),
        ]);

        return $result;
    }

    /**
     * Default implementation of writeBatch that calls write for each data point
     * Drivers should override this method with a more efficient implementation if possible
     *
     * This implementation collects errors and continues processing even if some writes fail.
     * It will return false if any write fails, but will attempt to write all data points.
     *
     * @param  DataPoint[]  $dataPoints  Array of data points to write
     * @return bool True if all data points were written successfully
     *
     * @throws WriteException If all writes fail with the same error
     */
    public function writeBatch(array $dataPoints): bool
    {
        if (empty($dataPoints)) {
            Logger::debug('Skipping empty batch write', [
                'driver' => $this->getDriverName(),
            ]);

            return true;
        }

        Logger::debug('Writing batch of data points', [
            'driver' => $this->getDriverName(),
            'count' => count($dataPoints),
            'measurements' => array_unique(array_map(fn ($dp) => $dp->getMeasurement(), $dataPoints)),
        ]);

        $success = true;
        $errors = [];

        foreach ($dataPoints as $index => $dataPoint) {
            try {
                if (! $this->write($dataPoint)) {
                    $success = false;
                    $errors[$index] = "Write failed for data point at index {$index}";
                    Logger::warning('Write failed for data point', [
                        'driver' => $this->getDriverName(),
                        'index' => $index,
                        'measurement' => $dataPoint->getMeasurement(),
                    ]);
                }
            } catch (\Throwable $e) {
                $success = false;
                $errors[$index] = $e->getMessage();
                Logger::error('Exception during write operation', [
                    'driver' => $this->getDriverName(),
                    'exception' => get_class($e),
                    'message' => $e->getMessage(),
                    'index' => $index,
                    'measurement' => $dataPoint->getMeasurement(),
                ]);
            }
        }

        // If all writes failed with the same error, throw an exception
        if (count($errors) === count($dataPoints) && count(array_unique($errors)) === 1) {
            Logger::error('All writes failed with the same error', [
                'driver' => $this->getDriverName(),
                'error' => reset($errors),
                'count' => count($dataPoints),
            ]);
            throw new WriteException(reset($errors));
        }

        Logger::debug('Batch write completed', [
            'driver' => $this->getDriverName(),
            'success' => $success,
            'total' => count($dataPoints),
            'errors' => count($errors),
        ]);

        return $success;
    }

    /**
     * Default implementation of getDatabases
     * Drivers should override this method with a proper implementation
     *
     * @return string[] Array of database names
     *
     * @throws DatabaseException If listing databases fails
     */
    public function getDatabases(): array
    {
        throw new DatabaseException('Method not implemented');
    }

    /**
     * Default implementation of deleteDatabase
     * Drivers should override this method with a proper implementation
     *
     * @param  string  $database  Name of the database to delete
     * @return bool True if database was deleted successfully
     *
     * @throws DatabaseException If database deletion fails
     */
    public function deleteDatabase(string $database): bool
    {
        throw new DatabaseException('Method not implemented');
    }

    /**
     * Default implementation of deleteMeasurement
     * Drivers should override this method with a proper implementation
     *
     * @param  string  $measurement  Name of the measurement to delete
     * @param  DateTime|null  $start  Optional start time for deletion range
     * @param  DateTime|null  $stop  Optional end time for deletion range
     * @return bool True if measurement was deleted successfully
     *
     * @throws DatabaseException If measurement deletion fails
     */
    public function deleteMeasurement(string $measurement, ?DateTime $start = null, ?DateTime $stop = null): bool
    {
        throw new DatabaseException('Method not implemented');
    }
}

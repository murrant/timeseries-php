<?php

namespace TimeSeriesPhp\Core;

use DateTime;
use TimeSeriesPhp\Config\ConfigInterface;
use TimeSeriesPhp\Exceptions\DatabaseException;

abstract class AbstractTimeSeriesDB implements TimeSeriesInterface
{
    protected ConfigInterface $config;

    protected bool $connected = false;

    protected QueryBuilderInterface $queryBuilder;

    public function isConnected(): bool
    {
        return $this->connected;
    }

    public function connect(ConfigInterface $config): bool
    {
        $this->config = $config;

        \TimeSeriesPhp\Utils\Logger::info('Connecting to time series database', [
            'driver' => basename(str_replace('\\', '/', get_class($this))),
        ]);

        return $this->doConnect();
    }

    abstract protected function doConnect(): bool;

    public function query(Query $query): QueryResult
    {
        \TimeSeriesPhp\Utils\Logger::debug('Executing query', [
            'driver' => basename(str_replace('\\', '/', get_class($this))),
            'measurement' => $query->getMeasurement(),
            'fields' => $query->getFields(),
            'conditions' => $query->getConditions(),
        ]);

        $rawQuery = $this->queryBuilder->build($query);
        $result = $this->rawQuery($rawQuery);

        \TimeSeriesPhp\Utils\Logger::debug('Query executed', [
            'driver' => basename(str_replace('\\', '/', get_class($this))),
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
            \TimeSeriesPhp\Utils\Logger::debug('Skipping empty batch write', [
                'driver' => basename(str_replace('\\', '/', get_class($this))),
            ]);

            return true;
        }

        \TimeSeriesPhp\Utils\Logger::debug('Writing batch of data points', [
            'driver' => basename(str_replace('\\', '/', get_class($this))),
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
                    \TimeSeriesPhp\Utils\Logger::warning('Write failed for data point', [
                        'driver' => basename(str_replace('\\', '/', get_class($this))),
                        'index' => $index,
                        'measurement' => $dataPoint->getMeasurement(),
                    ]);
                }
            } catch (\Throwable $e) {
                $success = false;
                $errors[$index] = $e->getMessage();
                \TimeSeriesPhp\Utils\Logger::error('Exception during write operation', [
                    'driver' => basename(str_replace('\\', '/', get_class($this))),
                    'exception' => get_class($e),
                    'message' => $e->getMessage(),
                    'index' => $index,
                    'measurement' => $dataPoint->getMeasurement(),
                ]);
            }
        }

        // If all writes failed with the same error, throw an exception
        if (count($errors) === count($dataPoints) && count(array_unique($errors)) === 1) {
            \TimeSeriesPhp\Utils\Logger::error('All writes failed with the same error', [
                'driver' => basename(str_replace('\\', '/', get_class($this))),
                'error' => reset($errors),
                'count' => count($dataPoints),
            ]);
            throw new \TimeSeriesPhp\Exceptions\WriteException(reset($errors));
        }

        \TimeSeriesPhp\Utils\Logger::debug('Batch write completed', [
            'driver' => basename(str_replace('\\', '/', get_class($this))),
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

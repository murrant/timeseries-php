<?php

namespace TimeSeriesPhp\Core\Driver;

use DateTime;
use Psr\Log\LoggerInterface;
use ReflectionClass;
use TimeSeriesPhp\Contracts\Driver\TimeSeriesInterface;
use TimeSeriesPhp\Contracts\Query\QueryBuilderInterface;
use TimeSeriesPhp\Core\Attributes\Driver;
use TimeSeriesPhp\Core\Data\DataPoint;
use TimeSeriesPhp\Core\Data\QueryResult;
use TimeSeriesPhp\Core\Query\Query;
use TimeSeriesPhp\Exceptions\Driver\DatabaseException;
use TimeSeriesPhp\Exceptions\Driver\WriteException;

abstract class AbstractTimeSeriesDB implements TimeSeriesInterface
{
    protected string $driverName = '';

    /**
     * @var QueryBuilderInterface|null The query builder factory to use for creating query builders
     */
    protected ?QueryBuilderInterface $queryBuilderFactory;

    public function __construct(
        protected QueryBuilderInterface $queryBuilder,
        protected LoggerInterface $logger
    ) {
    }

    /**
     * Get the driver name as stored or from the attribute
     */
    protected function getDriverName(): string
    {
        if ($this->driverName === '') {
            $reflection = new ReflectionClass(static::class);
            $attributes = $reflection->getAttributes(Driver::class);

            if (!empty($attributes)) {
                /** @var Driver $driver */
                $driver = $attributes[0]->newInstance();
                $this->driverName = $driver->name;
            }
        }

        return $this->driverName;
    }

    public function connect(): bool
    {
        $this->logger->info('Connecting to time series database', [
            'driver' => $this->getDriverName(),
        ]);

        return $this->doConnect();
    }

    abstract protected function doConnect(): bool;

    /**
     * Write a single data point to the database
     *
     * @param  DataPoint  $dataPoint  The data point to write
     * @return bool True if write was successful
     *
     * @throws WriteException If write fails
     */
    final public function write(DataPoint $dataPoint): bool
    {
        $this->logger->debug('Writing data point', [
            'driver' => $this->getDriverName(),
            'measurement' => $dataPoint->getMeasurement(),
            'tags' => $dataPoint->getTags(),
            'fields_count' => count($dataPoint->getFields()),
        ]);

        try {
            $result = $this->doWrite($dataPoint);

            $this->logger->debug('Write completed', [
                'driver' => $this->getDriverName(),
                'success' => $result,
                'measurement' => $dataPoint->getMeasurement(),
            ]);

            return $result;
        } catch (\Throwable $e) {
            $this->logger->error('Write failed with exception', [
                'driver' => $this->getDriverName(),
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'measurement' => $dataPoint->getMeasurement(),
            ]);

            if ($e instanceof WriteException) {
                throw $e;
            }

            throw new WriteException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Implementation of writing a single data point to the database
     *
     * @param  DataPoint  $dataPoint  The data point to write
     * @return bool True if write was successful
     *
     * @throws WriteException If write fails
     */
    abstract protected function doWrite(DataPoint $dataPoint): bool;

    public function query(Query $query): QueryResult
    {
        $this->logger->debug('Executing query', [
            'driver' => $this->getDriverName(),
            'measurement' => $query->getMeasurement(),
            'fields' => $query->getFields(),
            'conditions' => $query->getConditions(),
        ]);

        $rawQuery = $this->queryBuilder->build($query);
        $result = $this->rawQuery($rawQuery);

        $this->logger->debug('Query executed', [
            'driver' => $this->getDriverName(),
            'points_count' => $result->count(),
        ]);

        return $result;
    }

    /**
     * Write multiple data points to the database in a single operation
     *
     * @param  DataPoint[]  $dataPoints  Array of data points to write
     * @return bool True if all data points were written successfully
     *
     * @throws WriteException If write fails
     */
    final public function writeBatch(array $dataPoints): bool
    {
        if (empty($dataPoints)) {
            $this->logger->debug('Skipping empty batch write', [
                'driver' => $this->getDriverName(),
            ]);

            return true;
        }

        $this->logger->debug('Writing batch of data points', [
            'driver' => $this->getDriverName(),
            'count' => count($dataPoints),
            'measurements' => array_unique(array_map(fn ($dp) => $dp->getMeasurement(), $dataPoints)),
        ]);

        try {
            $result = $this->doWriteBatch($dataPoints);

            $this->logger->debug('Batch write completed', [
                'driver' => $this->getDriverName(),
                'success' => $result,
                'total' => count($dataPoints),
            ]);

            return $result;
        } catch (\Throwable $e) {
            $this->logger->error('Batch write failed with exception', [
                'driver' => $this->getDriverName(),
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'count' => count($dataPoints),
            ]);

            if ($e instanceof WriteException) {
                throw $e;
            }

            throw new WriteException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Implementation of writing multiple data points to the database
     *
     * Default implementation calls write for each data point.
     * Drivers should override this method with a more efficient implementation if possible.
     *
     * @param  DataPoint[]  $dataPoints  Array of data points to write
     * @return bool True if all data points were written successfully
     *
     * @throws WriteException If write fails
     */
    protected function doWriteBatch(array $dataPoints): bool
    {
        $success = true;
        $errors = [];

        foreach ($dataPoints as $index => $dataPoint) {
            try {
                if (! $this->doWrite($dataPoint)) {
                    $success = false;
                    $errors[$index] = "Write failed for data point at index {$index}";
                    $this->logger->warning('Write failed for data point', [
                        'driver' => $this->getDriverName(),
                        'index' => $index,
                        'measurement' => $dataPoint->getMeasurement(),
                    ]);
                }
            } catch (\Throwable $e) {
                $success = false;
                $errors[$index] = $e->getMessage();
                $this->logger->error('Exception during write operation', [
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
            $error = reset($errors);
            throw new WriteException($error !== false ? $error : 'Unknown error');
        }

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

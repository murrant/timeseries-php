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

        return $this->doConnect();
    }

    abstract protected function doConnect(): bool;

    public function query(Query $query): QueryResult
    {
        return $this->rawQuery($this->queryBuilder->build($query));
    }

    /**
     * Default implementation of writeBatch that calls write for each data point
     * Drivers should override this method with a more efficient implementation if possible
     *
     * @param  DataPoint[]  $dataPoints  Array of data points to write
     * @return bool True if all data points were written successfully
     */
    public function writeBatch(array $dataPoints): bool
    {
        foreach ($dataPoints as $dataPoint) {
            if (! $this->write($dataPoint)) {
                return false;
            }
        }

        return true;
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

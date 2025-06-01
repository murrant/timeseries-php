<?php

namespace TimeSeriesPhp\Drivers\Aggregate;

use DateTime;
use Psr\Log\LoggerInterface;
use TimeSeriesPhp\Contracts\Driver\ConfigurableInterface;
use TimeSeriesPhp\Contracts\Driver\TimeSeriesInterface;
use TimeSeriesPhp\Contracts\Query\RawQueryInterface;
use TimeSeriesPhp\Core\Attributes\Driver;
use TimeSeriesPhp\Core\Data\DataPoint;
use TimeSeriesPhp\Core\Data\QueryResult;
use TimeSeriesPhp\Core\Driver\AbstractTimeSeriesDB;
use TimeSeriesPhp\Core\Driver\DriverFactory;
use TimeSeriesPhp\Drivers\Aggregate\Config\AggregateConfig;
use TimeSeriesPhp\Drivers\Null\NullQueryBuilder;
use TimeSeriesPhp\Exceptions\Driver\ConnectionException;
use TimeSeriesPhp\Exceptions\Driver\DatabaseException;
use TimeSeriesPhp\Exceptions\Driver\WriteException;
use TimeSeriesPhp\Exceptions\Query\RawQueryException;

#[Driver(name: 'aggregate', configClass: AggregateConfig::class)]
class AggregateDriver extends AbstractTimeSeriesDB implements ConfigurableInterface
{
    /** @var TimeSeriesInterface[] */
    protected array $writeDatabases = [];

    protected ?TimeSeriesInterface $readDatabase = null;

    /**
     * @var bool Whether the driver is connected
     */
    protected bool $connected = false;

    public function __construct(
        protected DriverFactory $driverFactory,
        protected AggregateConfig $config,
        LoggerInterface $logger,
    ) {
        parent::__construct(new NullQueryBuilder, $logger); // this will send all queries to the read database
    }

    /**
     * Configure the driver with the given configuration
     *
     * @param  array<string, mixed>  $config
     */
    public function configure(array $config): void
    {
        $this->config = $this->config->createFromArray($config);
    }

    /**
     * @throws ConnectionException
     */
    protected function doConnect(): bool
    {

        // Connect to write databases
        $writeDatabaseConfigs = $this->config->getWriteDatabases();
        foreach ($writeDatabaseConfigs as $dbConfig) {
            try {
                if (! isset($dbConfig['driver']) || ! is_string($dbConfig['driver']) && ! is_numeric($dbConfig['driver'])) {
                    throw new ConnectionException('Driver must be a string');
                }
                $driver = (string) $dbConfig['driver'];

                $db = $this->driverFactory->create($driver);
                $this->writeDatabases[] = $db;

                $this->logger->info('Connected to write database', [
                    'driver' => $this->getDriverName(),
                    'write_driver' => $driver,
                ]);
            } catch (\Throwable $e) {
                $this->logger->error('Failed to connect to write database', [
                    'driver' => $this->getDriverName(),
                    'write_driver' => $dbConfig['driver'] ?? 'unknown',
                    'error' => $e->getMessage(),
                ]);
                throw new ConnectionException('Failed to connect to write database: '.$e->getMessage(), 0, $e);
            }
        }

        // Connect to read database if configured, otherwise use the first write database
        $readDatabaseConfig = $this->config->getReadDatabase();
        if ($readDatabaseConfig !== null) {
            try {
                if (! isset($readDatabaseConfig['driver']) || ! is_string($readDatabaseConfig['driver']) && ! is_numeric($readDatabaseConfig['driver'])) {
                    throw new ConnectionException('Driver must be a string');
                }
                $driver = (string) $readDatabaseConfig['driver'];

                /** @var array<string, mixed> $readConfig */
                $readConfig = $readDatabaseConfig['config'] ?? [];
                $this->readDatabase = $this->driverFactory->create($driver, $readConfig);

                $this->logger->info('Connected to read database', [
                    'driver' => $this->getDriverName(),
                    'read_driver' => $driver,
                ]);
            } catch (\Throwable $e) {
                $this->logger->error('Failed to connect to read database', [
                    'driver' => $this->getDriverName(),
                    'read_driver' => $readDatabaseConfig['driver'] ?? 'unknown',
                    'error' => $e->getMessage(),
                ]);
                throw new ConnectionException('Failed to connect to read database: '.$e->getMessage(), 0, $e);
            }
        } elseif (! empty($this->writeDatabases)) {
            // Use the first write database for reading if no read database is configured
            $this->readDatabase = $this->writeDatabases[0];

            $this->logger->info('Using first write database for reading', [
                'driver' => $this->getDriverName(),
            ]);
        } else {
            throw new ConnectionException('No databases available for reading');
        }

        $this->connected = true;

        return true;
    }

    /**
     * @throws WriteException
     */
    protected function doWrite(DataPoint $dataPoint): bool
    {
        if (empty($this->writeDatabases)) {
            throw new WriteException('No write databases configured');
        }

        $success = true;
        $errors = [];

        foreach ($this->writeDatabases as $index => $db) {
            try {
                if (! $db->write($dataPoint)) {
                    $success = false;
                    $errors[$index] = "Write failed for database at index {$index}";
                }
            } catch (\Throwable $e) {
                $success = false;
                $errors[$index] = $e->getMessage();
            }
        }

        // If all writes failed with the same error, throw an exception
        if (count($errors) === count($this->writeDatabases) && count(array_unique($errors)) === 1) {
            throw new WriteException(reset($errors));
        }

        return $success;
    }

    /**
     * @throws WriteException
     */
    protected function doWriteBatch(array $dataPoints): bool
    {
        if (empty($this->writeDatabases)) {
            throw new WriteException('No write databases configured');
        }

        $success = true;
        $errors = [];

        foreach ($this->writeDatabases as $index => $db) {
            try {
                if (! $db->writeBatch($dataPoints)) {
                    $success = false;
                    $errors[$index] = "Batch write failed for database at index {$index}";
                }
            } catch (\Throwable $e) {
                $success = false;
                $errors[$index] = $e->getMessage();
            }
        }

        // If all writes failed with the same error, throw an exception
        if (count($errors) === count($this->writeDatabases) && count(array_unique($errors)) === 1) {
            throw new WriteException(reset($errors));
        }

        return $success;
    }

    /**
     * @throws RawQueryException
     */
    public function rawQuery(RawQueryInterface $query): QueryResult
    {
        if ($this->readDatabase === null) {
            throw new RawQueryException($query, 'No read database available');
        }

        return $this->readDatabase->rawQuery($query);
    }

    /**
     * @throws DatabaseException
     */
    public function createDatabase(string $database): bool
    {
        if (empty($this->writeDatabases)) {
            throw new DatabaseException('No write databases configured');
        }

        $success = true;
        $errors = [];

        foreach ($this->writeDatabases as $index => $db) {
            try {
                if (! $db->createDatabase($database)) {
                    $success = false;
                    $errors[$index] = "Create database failed for database at index {$index}";
                }
            } catch (\Throwable $e) {
                $success = false;
                $errors[$index] = $e->getMessage();
            }
        }

        // If all operations failed with the same error, throw an exception
        if (count($errors) === count($this->writeDatabases) && count(array_unique($errors)) === 1) {
            throw new DatabaseException(reset($errors));
        }

        return $success;
    }

    /**
     * @throws DatabaseException
     */
    public function getDatabases(): array
    {
        if ($this->readDatabase === null) {
            throw new DatabaseException('No read database available');
        }

        return $this->readDatabase->getDatabases();
    }

    /**
     * @throws DatabaseException
     */
    public function deleteDatabase(string $database): bool
    {
        if (empty($this->writeDatabases)) {
            throw new DatabaseException('No write databases configured');
        }

        $success = true;
        $errors = [];

        foreach ($this->writeDatabases as $index => $db) {
            try {
                if (! $db->deleteDatabase($database)) {
                    $success = false;
                    $errors[$index] = "Delete database failed for database at index {$index}";
                }
            } catch (\Throwable $e) {
                $success = false;
                $errors[$index] = $e->getMessage();
            }
        }

        // If all operations failed with the same error, throw an exception
        if (count($errors) === count($this->writeDatabases) && count(array_unique($errors)) === 1) {
            throw new DatabaseException(reset($errors));
        }

        return $success;
    }

    /**
     * @throws DatabaseException
     */
    public function deleteMeasurement(string $measurement, ?DateTime $start = null, ?DateTime $stop = null): bool
    {
        if (empty($this->writeDatabases)) {
            throw new DatabaseException('No write databases configured');
        }

        $success = true;
        $errors = [];

        foreach ($this->writeDatabases as $index => $db) {
            try {
                if (! $db->deleteMeasurement($measurement, $start, $stop)) {
                    $success = false;
                    $errors[$index] = "Delete measurement failed for database at index {$index}";
                }
            } catch (\Throwable $e) {
                $success = false;
                $errors[$index] = $e->getMessage();
            }
        }

        // If all operations failed with the same error, throw an exception
        if (count($errors) === count($this->writeDatabases) && count(array_unique($errors)) === 1) {
            throw new DatabaseException(reset($errors));
        }

        return $success;
    }

    public function close(): void
    {
        foreach ($this->writeDatabases as $db) {
            $db->close();
        }

        if ($this->readDatabase !== null && ! in_array($this->readDatabase, $this->writeDatabases, true)) {
            $this->readDatabase->close();
        }

        $this->connected = false;
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
}

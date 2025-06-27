<?php

namespace TimeSeriesPhp\Drivers\InfluxDB\Schema;

use Psr\Log\LoggerInterface;
use TimeSeriesPhp\Core\Schema\AbstractSchemaManager;
use TimeSeriesPhp\Core\Schema\MeasurementSchema;
use TimeSeriesPhp\Drivers\InfluxDB\InfluxDBDriver;
use TimeSeriesPhp\Drivers\InfluxDB\InfluxDBRawQuery;
use TimeSeriesPhp\Exceptions\Schema\SchemaException;

/**
 * Schema manager for InfluxDB
 */
class InfluxDBSchemaManager extends AbstractSchemaManager
{
    /**
     * @var array<string, bool> Cache of measurement existence
     */
    private array $measurementExistsCache = [];

    /**
     * @var array<string> Cache of applied migrations
     */
    private array $appliedMigrationsCache = [];

    /**
     * @param  InfluxDBDriver  $driver  The InfluxDB driver
     * @param  LoggerInterface  $logger  Logger for recording operations
     */
    public function __construct(
        private readonly InfluxDBDriver $driver,
        LoggerInterface $logger
    ) {
        parent::__construct($logger);
    }

    /**
     * {@inheritdoc}
     */
    public function listMeasurements(): array
    {
        try {
            $this->logger->debug('Listing measurements');

            // Execute a SHOW MEASUREMENTS query
            $query = 'SHOW MEASUREMENTS';
            $result = $this->driver->rawQuery(new InfluxDBRawQuery($query));

            $measurements = [];
            foreach ($result->getSeries() as $series) {
                foreach ($series->getValues() as $value) {
                    $measurements[] = $value[0];
                }
            }

            return $measurements;
        } catch (\Exception $e) {
            $this->logger->error("Error listing measurements: {$e->getMessage()}");
            throw new SchemaException("Failed to list measurements: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function measurementExists(string $measurement): bool
    {
        if (isset($this->measurementExistsCache[$measurement])) {
            return $this->measurementExistsCache[$measurement];
        }

        try {
            $this->logger->debug("Checking if measurement exists: {$measurement}");

            // Execute a SHOW MEASUREMENTS query with a filter
            $query = "SHOW MEASUREMENTS WHERE name = '{$measurement}'";
            $result = $this->driver->rawQuery(new InfluxDBRawQuery($query));

            $exists = false;
            foreach ($result->getSeries() as $series) {
                foreach ($series->getValues() as $value) {
                    if ($value[0] === $measurement) {
                        $exists = true;
                        break 2;
                    }
                }
            }

            $this->measurementExistsCache[$measurement] = $exists;

            return $exists;
        } catch (\Exception $e) {
            $this->logger->error("Error checking if measurement exists: {$e->getMessage()}");
            throw new SchemaException("Failed to check if measurement exists: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getAppliedMigrations(): array
    {
        if (! empty($this->appliedMigrationsCache)) {
            return $this->appliedMigrationsCache;
        }

        try {
            $this->logger->debug('Getting applied migrations');

            // Check if the migrations measurement exists
            if (! $this->measurementExists('schema_migrations')) {
                // Create the migrations measurement if it doesn't exist
                $this->createMigrationsMeasurement();

                return [];
            }

            // Execute a SELECT query to get all migrations
            $query = 'SELECT migration_name FROM schema_migrations';
            $result = $this->driver->rawQuery(new InfluxDBRawQuery($query));

            $migrations = [];
            foreach ($result->getSeries() as $series) {
                foreach ($series->getValues() as $value) {
                    $migrations[] = $value[1]; // migration_name is the second column
                }
            }

            $this->appliedMigrationsCache = $migrations;

            return $migrations;
        } catch (\Exception $e) {
            $this->logger->error("Error getting applied migrations: {$e->getMessage()}");
            throw new SchemaException("Failed to get applied migrations: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function doCreateMeasurement(MeasurementSchema $schema): bool
    {
        try {
            $measurementName = $schema->getName();
            $this->logger->debug("Creating measurement: {$measurementName}");

            // InfluxDB doesn't require explicit measurement creation, but we can store the schema
            // in a special measurement for schema tracking
            $schemaData = $schema->toArray();
            $schemaJson = json_encode($schemaData);

            // Store the schema in a special measurement
            $query = "INSERT schema_registry,measurement_name=\"{$measurementName}\" schema='{$schemaJson}'";
            $this->driver->rawQuery(new InfluxDBRawQuery($query));

            $this->measurementExistsCache[$measurementName] = true;

            return true;
        } catch (\Exception $e) {
            $this->logger->error("Error creating measurement: {$e->getMessage()}");
            throw new SchemaException("Failed to create measurement: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function doUpdateMeasurement(MeasurementSchema $schema): bool
    {
        try {
            $measurementName = $schema->getName();
            $this->logger->debug("Updating measurement: {$measurementName}");

            // InfluxDB doesn't support altering measurements directly, but we can update the schema
            // in our schema registry
            $schemaData = $schema->toArray();
            $schemaJson = json_encode($schemaData);

            // Update the schema in the schema registry
            $query = "INSERT schema_registry,measurement_name=\"{$measurementName}\" schema='{$schemaJson}'";
            $this->driver->rawQuery(new InfluxDBRawQuery($query));

            return true;
        } catch (\Exception $e) {
            $this->logger->error("Error updating measurement: {$e->getMessage()}");
            throw new SchemaException("Failed to update measurement: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function doGetMeasurementSchema(string $measurement): MeasurementSchema
    {
        try {
            $this->logger->debug("Getting schema for measurement: {$measurement}");

            // Check if the schema registry measurement exists
            if (! $this->measurementExists('schema_registry')) {
                throw new SchemaException('Schema registry does not exist');
            }

            // Query the schema registry for the measurement schema
            $query = "SELECT schema FROM schema_registry WHERE measurement_name = '{$measurement}' ORDER BY time DESC LIMIT 1";
            $result = $this->driver->rawQuery(new InfluxDBRawQuery($query));

            if ($result->isEmpty()) {
                throw new SchemaException("Schema for measurement '{$measurement}' not found");
            }

            $schemaJson = null;
            foreach ($result->getSeries() as $series) {
                foreach ($series->getValues() as $value) {
                    $schemaJson = $value[1]; // schema is the second column
                    break 2;
                }
            }

            if ($schemaJson === null) {
                throw new SchemaException("Schema for measurement '{$measurement}' not found");
            }

            $schemaData = json_decode($schemaJson, true);
            if ($schemaData === null) {
                throw new SchemaException("Invalid schema JSON for measurement '{$measurement}'");
            }

            return MeasurementSchema::fromArray($schemaData);
        } catch (\Exception $e) {
            $this->logger->error("Error getting measurement schema: {$e->getMessage()}");
            throw new SchemaException("Failed to get measurement schema: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function doApplyMigration(string $migrationName): bool
    {
        try {
            $this->logger->debug("Applying migration: {$migrationName}");

            // Check if the migrations measurement exists
            if (! $this->measurementExists('schema_migrations')) {
                $this->createMigrationsMeasurement();
            }

            // Record the migration as applied
            $query = "INSERT schema_migrations,migration_name=\"{$migrationName}\" applied=true";
            $this->driver->rawQuery(new InfluxDBRawQuery($query));

            // Update the applied migrations cache
            $this->appliedMigrationsCache[] = $migrationName;

            return true;
        } catch (\Exception $e) {
            $this->logger->error("Error applying migration: {$e->getMessage()}");
            throw new SchemaException("Failed to apply migration: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Create the migrations measurement if it doesn't exist
     *
     * @throws SchemaException If creating the migrations measurement fails
     */
    private function createMigrationsMeasurement(): void
    {
        try {
            $this->logger->debug('Creating migrations measurement');

            // InfluxDB doesn't require explicit measurement creation, but we can insert a dummy record
            $query = 'INSERT schema_migrations,migration_name="init" applied=true';
            $this->driver->rawQuery(new InfluxDBRawQuery($query));

            $this->measurementExistsCache['schema_migrations'] = true;
        } catch (\Exception $e) {
            $this->logger->error("Error creating migrations measurement: {$e->getMessage()}");
            throw new SchemaException("Failed to create migrations measurement: {$e->getMessage()}", 0, $e);
        }
    }
}

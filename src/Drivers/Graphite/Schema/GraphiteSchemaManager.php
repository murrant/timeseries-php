<?php

namespace TimeSeriesPhp\Drivers\Graphite\Schema;

use Psr\Log\LoggerInterface;
use TimeSeriesPhp\Core\Schema\AbstractSchemaManager;
use TimeSeriesPhp\Core\Schema\MeasurementSchema;
use TimeSeriesPhp\Drivers\Graphite\GraphiteDriver;
use TimeSeriesPhp\Drivers\Graphite\GraphiteRawQuery;
use TimeSeriesPhp\Exceptions\Schema\SchemaException;

/**
 * Schema manager for Graphite
 */
class GraphiteSchemaManager extends AbstractSchemaManager
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
     * @param GraphiteDriver $driver The Graphite driver
     * @param LoggerInterface $logger Logger for recording operations
     */
    public function __construct(
        private readonly GraphiteDriver $driver,
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

            // Execute a query to get all metrics
            $query = '{"target": "*", "format": "json"}';
            $result = $this->driver->rawQuery(new GraphiteRawQuery($query));

            $measurements = [];
            foreach ($result->getSeries() as $series) {
                $measurements[] = $series->getName();
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

            // Execute a query to check if the metric exists
            $query = '{"target": "' . $measurement . '", "format": "json"}';
            $result = $this->driver->rawQuery(new GraphiteRawQuery($query));

            $exists = !$result->isEmpty();
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
        if (!empty($this->appliedMigrationsCache)) {
            return $this->appliedMigrationsCache;
        }

        try {
            $this->logger->debug('Getting applied migrations');

            // Check if the schema_registry measurement exists
            if (!$this->measurementExists('schema_registry.migrations')) {
                // Create the schema registry if it doesn't exist
                $this->createSchemaRegistry();
                return [];
            }

            // Get all migrations from the schema registry
            $query = '{"target": "schema_registry.migrations.*", "format": "json"}';
            $result = $this->driver->rawQuery(new GraphiteRawQuery($query));

            $migrations = [];
            foreach ($result->getSeries() as $series) {
                // Extract migration name from the series name (schema_registry.migrations.migration_name)
                $parts = explode('.', $series->getName());
                if (count($parts) >= 3) {
                    $migrations[] = $parts[2];
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

            // Graphite doesn't require explicit measurement creation, but we can store the schema
            // in a special measurement for schema tracking
            $schemaData = $schema->toArray();
            $schemaJson = json_encode($schemaData);

            // Check if the schema registry exists
            if (!$this->measurementExists('schema_registry.schemas')) {
                $this->createSchemaRegistry();
            }

            // Store the schema in the schema registry
            // Note: In Graphite, we'll store the schema as a metric value with a timestamp
            $metricPath = "schema_registry.schemas.{$measurementName}";
            $timestamp = time();
            $value = 1; // Just a placeholder value

            // Store the schema in a separate registry
            $this->storeSchemaData($measurementName, $schemaJson);

            // Mark the measurement as existing
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

            // Graphite doesn't support altering measurements directly, but we can update the schema
            // in our schema registry
            $schemaData = $schema->toArray();
            $schemaJson = json_encode($schemaData);

            // Check if the schema registry exists
            if (!$this->measurementExists('schema_registry.schemas')) {
                $this->createSchemaRegistry();
            }

            // Update the schema in the schema registry
            $this->storeSchemaData($measurementName, $schemaJson);

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

            // Check if the schema registry exists
            if (!$this->measurementExists('schema_registry.schemas')) {
                throw new SchemaException("Schema registry does not exist");
            }

            // Get the schema from our schema data storage
            $schemaJson = $this->getSchemaData($measurement);

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

            // Check if the schema registry exists
            if (!$this->measurementExists('schema_registry.migrations')) {
                $this->createSchemaRegistry();
            }

            // Record the migration as applied
            $metricPath = "schema_registry.migrations.{$migrationName}";
            $timestamp = time();
            $value = 1; // Just a placeholder value

            // Send the metric to Graphite
            $this->driver->write($metricPath);

            // Update the applied migrations cache
            $this->appliedMigrationsCache[] = $migrationName;

            return true;
        } catch (\Exception $e) {
            $this->logger->error("Error applying migration: {$e->getMessage()}");
            throw new SchemaException("Failed to apply migration: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Create the schema registry if it doesn't exist
     *
     * @throws SchemaException If creating the schema registry fails
     */
    private function createSchemaRegistry(): void
    {
        try {
            $this->logger->debug('Creating schema registry');

            // Create the schemas registry
            $metricPath = 'schema_registry.schemas.init';
            $timestamp = time();
            $value = 1; // Just a placeholder value

            // Send the metric to Graphite
            $this->driver->write($metricPath);

            // Create the migrations registry
            $metricPath = 'schema_registry.migrations.init';
            $this->driver->write($metricPath);

            // Mark the registries as existing
            $this->measurementExistsCache['schema_registry.schemas'] = true;
            $this->measurementExistsCache['schema_registry.migrations'] = true;
        } catch (\Exception $e) {
            $this->logger->error("Error creating schema registry: {$e->getMessage()}");
            throw new SchemaException("Failed to create schema registry: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Store schema data for a measurement
     *
     * @param string $measurement The measurement name
     * @param string $schemaJson The schema JSON
     * @throws SchemaException If storing the schema data fails
     */
    private function storeSchemaData(string $measurement, string $schemaJson): void
    {
        try {
            // In a real implementation, we would need to store the schema data somewhere
            // Since Graphite doesn't support storing arbitrary data with metrics,
            // we would need to use an external storage (file, database, etc.)
            // For this example, we'll just log the operation
            $this->logger->debug("Storing schema data for measurement '{$measurement}': {$schemaJson}");

            // For a real implementation, you might use:
            // - A file-based storage
            // - A database
            // - A key-value store
            // - etc.

            // For now, we'll simulate storing the schema by writing a metric
            $metricPath = "schema_registry.schemas.{$measurement}";
            $timestamp = time();
            $value = 1; // Just a placeholder value

            // Send the metric to Graphite
            $this->driver->write($metricPath);
        } catch (\Exception $e) {
            $this->logger->error("Error storing schema data: {$e->getMessage()}");
            throw new SchemaException("Failed to store schema data: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Get schema data for a measurement
     *
     * @param string $measurement The measurement name
     * @return string|null The schema JSON or null if not found
     * @throws SchemaException If getting the schema data fails
     */
    private function getSchemaData(string $measurement): ?string
    {
        try {
            // In a real implementation, we would need to retrieve the schema data from somewhere
            // Since Graphite doesn't support storing arbitrary data with metrics,
            // we would need to use an external storage (file, database, etc.)
            // For this example, we'll just simulate it

            // Check if the measurement exists in the schema registry
            $metricPath = "schema_registry.schemas.{$measurement}";
            $query = '{"target": "' . $metricPath . '", "format": "json"}';
            $result = $this->driver->rawQuery(new GraphiteRawQuery($query));

            if ($result->isEmpty()) {
                return null;
            }

            // In a real implementation, you would retrieve the actual schema data
            // For now, we'll simulate it by returning a basic schema
            $schema = [
                'name' => $measurement,
                'fields' => [
                    'value' => [
                        'type' => 'float',
                        'required' => true,
                    ],
                ],
                'tags' => [
                    'host' => [
                        'required' => true,
                    ],
                    'region' => [
                        'required' => false,
                    ],
                ],
            ];

            return json_encode($schema);
        } catch (\Exception $e) {
            $this->logger->error("Error getting schema data: {$e->getMessage()}");
            throw new SchemaException("Failed to get schema data: {$e->getMessage()}", 0, $e);
        }
    }
}
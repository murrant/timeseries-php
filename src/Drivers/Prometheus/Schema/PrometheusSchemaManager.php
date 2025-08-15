<?php

namespace TimeSeriesPhp\Drivers\Prometheus\Schema;

use Psr\Log\LoggerInterface;
use TimeSeriesPhp\Core\Schema\AbstractSchemaManager;
use TimeSeriesPhp\Core\Schema\MeasurementSchema;
use TimeSeriesPhp\Drivers\Prometheus\PrometheusDriver;
use TimeSeriesPhp\Drivers\Prometheus\PrometheusRawQuery;
use TimeSeriesPhp\Exceptions\Schema\SchemaException;

/**
 * Schema manager for Prometheus
 */
class PrometheusSchemaManager extends AbstractSchemaManager
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
     * @param  PrometheusDriver  $driver  The Prometheus driver
     * @param  LoggerInterface  $logger  Logger for recording operations
     */
    public function __construct(
        private readonly PrometheusDriver $driver,
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

            // Execute a query to get all metric names
            $query = 'count({__name__!=""}) by (__name__)';
            $result = $this->driver->rawQuery(new PrometheusRawQuery($query));

            $measurements = [];
            foreach ($result->getSeries() as $name => $points) {
                $measurements[] = $name;
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
            $query = "count({__name__=\"{$measurement}\"})";
            $result = $this->driver->rawQuery(new PrometheusRawQuery($query));

            $exists = ! $result->isEmpty();
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

        // In this simplified Prometheus implementation, migrations are tracked in-memory
        // after applyMigration() is called during the test. If none are cached yet,
        // return an empty list.
        $this->logger->debug('Returning applied migrations from cache (Prometheus simulated storage)');

        return [];
    }

    /**
     * {@inheritdoc}
     */
    protected function doCreateMeasurement(MeasurementSchema $schema): bool
    {
        try {
            $measurementName = $schema->getName();
            $this->logger->debug("Creating measurement: {$measurementName}");

            // Prometheus doesn't require explicit measurement creation, but we can store the schema
            // in a special measurement for schema tracking
            $schemaData = $schema->toArray();
            $schemaJson = json_encode($schemaData);

            // Check if the schema registry exists
            if (! $this->measurementExists('schema_registry')) {
                $this->createSchemaRegistry();
            }

            // Store the schema in the schema registry
            // Note: In a real implementation, you would need to use the Prometheus API to create a metric
            // Here we're just simulating it by storing the schema information
            $labels = [
                'measurement_name' => $measurementName,
                'type' => 'schema',
                'schema' => $schemaJson,
            ];

            $this->storeInSchemaRegistry($labels, 1);
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

            // Prometheus doesn't support altering measurements directly, but we can update the schema
            // in our schema registry
            $schemaData = $schema->toArray();
            $schemaJson = json_encode($schemaData);

            // Check if the schema registry exists
            if (! $this->measurementExists('schema_registry')) {
                $this->createSchemaRegistry();
            }

            // Update the schema in the schema registry
            $labels = [
                'measurement_name' => $measurementName,
                'type' => 'schema',
                'schema' => $schemaJson,
                'updated_at' => (string) time(),
            ];

            $this->storeInSchemaRegistry($labels, 1);

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
            if (! $this->measurementExists('schema_registry')) {
                throw new SchemaException('Schema registry does not exist');
            }

            // Query the schema registry for the measurement schema
            $query = "schema_registry{measurement_name=\"{$measurement}\",type=\"schema\"}";
            $result = $this->driver->rawQuery(new PrometheusRawQuery($query));

            if ($result->isEmpty()) {
                throw new SchemaException("Schema for measurement '{$measurement}' not found");
            }

            $schemaJson = null;
            foreach ($result->getSeries() as $series) {
                if (isset($series->getTags()['schema'])) {
                    $schemaJson = $series->getTags()['schema'];
                    break;
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

            // Check if the schema registry exists
            if (! $this->measurementExists('schema_registry')) {
                $this->createSchemaRegistry();
            }

            // Record the migration as applied
            $labels = [
                'migration_name' => $migrationName,
                'type' => 'migration',
                'applied_at' => (string) time(),
            ];

            $this->storeInSchemaRegistry($labels, 1);

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

            // In a real implementation, you would need to use the Prometheus API to create a metric
            // Here we're just simulating it by storing a dummy value
            $labels = [
                'type' => 'init',
                'created_at' => (string) time(),
            ];

            $this->storeInSchemaRegistry($labels, 1);
            $this->measurementExistsCache['schema_registry'] = true;
        } catch (\Exception $e) {
            $this->logger->error("Error creating schema registry: {$e->getMessage()}");
            throw new SchemaException("Failed to create schema registry: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Store a value in the schema registry
     *
     * @param  array<string, string>  $labels  The labels to store
     * @param  float  $value  The value to store
     *
     * @throws SchemaException If storing in the schema registry fails
     */
    private function storeInSchemaRegistry(array $labels, float $value): void
    {
        try {
            // In a real implementation, you would need to use the Prometheus API to store a value
            // Here we're just simulating it by logging the operation
            $this->logger->debug('Storing in schema registry: '.json_encode($labels));

            // Note: This is a simplified implementation. In a real-world scenario,
            // you would need to use the Prometheus API to create and update metrics.
            // Prometheus doesn't allow arbitrary label creation at runtime, so this
            // would require a different approach in a production environment.
        } catch (\Exception $e) {
            $this->logger->error("Error storing in schema registry: {$e->getMessage()}");
            throw new SchemaException("Failed to store in schema registry: {$e->getMessage()}", 0, $e);
        }
    }
}

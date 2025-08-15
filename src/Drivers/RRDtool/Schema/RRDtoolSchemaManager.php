<?php

namespace TimeSeriesPhp\Drivers\RRDtool\Schema;

use Psr\Log\LoggerInterface;
use TimeSeriesPhp\Core\Schema\AbstractSchemaManager;
use TimeSeriesPhp\Core\Schema\MeasurementSchema;
use TimeSeriesPhp\Drivers\RRDtool\RRDtoolDriver;
use TimeSeriesPhp\Exceptions\Schema\SchemaException;

/**
 * Schema manager for RRDtool
 */
class RRDtoolSchemaManager extends AbstractSchemaManager
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
     * @var string Path to the schema registry file
     */
    private readonly string $schemaRegistryPath;

    /**
     * @var string Path to the migrations registry file
     */
    private readonly string $migrationsRegistryPath;

    /**
     * @param  RRDtoolDriver  $driver  The RRDtool driver
     * @param  LoggerInterface  $logger  Logger for recording operations
     * @throws SchemaException
     */
    public function __construct(
        private readonly RRDtoolDriver $driver,
        LoggerInterface $logger
    ) {
        parent::__construct($logger);

        // Set up paths for schema and migrations registries
        $dataDir = $this->driver->getDataDir();
        $this->ensureDirectoryFor($dataDir, 'data directory');
        $this->schemaRegistryPath = $dataDir.'/schema_registry.json';
        $this->migrationsRegistryPath = $dataDir.'/migrations_registry.json';
    }

    /**
     * {@inheritdoc}
     */
    public function listMeasurements(): array
    {
        try {
            $this->logger->debug('Listing measurements');

            // Get all RRD files in the data directory
            $dataDir = $this->driver->getDataDir();
            if (! is_dir($dataDir)) {
                return [];
            }
            $files = glob($dataDir.'/*.rrd') ?: [];

            $measurements = [];
            foreach ($files as $file) {
                // Extract the measurement name from the file path
                $basename = basename($file, '.rrd');
                if ($basename !== 'schema_registry' && $basename !== 'migrations_registry') {
                    $measurements[] = $basename;
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

            // Check if the RRD file exists
            $dataDir = $this->driver->getDataDir();
            $rrdFile = $dataDir.'/'.$measurement.'.rrd';

            $exists = file_exists($rrdFile);
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

            // Check if the migrations registry file exists
            if (! file_exists($this->migrationsRegistryPath)) {
                // Create the migrations registry if it doesn't exist
                $this->createMigrationsRegistry();

                return [];
            }

            // Read the migrations registry
            $migrationsJson = file_get_contents($this->migrationsRegistryPath);
            if ($migrationsJson === false) {
                throw new SchemaException('Failed to read migrations registry');
            }

            $migrations = json_decode($migrationsJson, true);
            if ($migrations === null) {
                throw new SchemaException('Invalid migrations registry JSON');
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

            // Store the schema in the schema registry
            $this->storeSchema($schema);

            // For RRDtool, we need to create an RRD file with the appropriate data sources and archives
            $dataDir = $this->driver->getDataDir();
            $rrdFile = $dataDir.'/'.$measurementName.'.rrd';

            $dataSources = $this->buildCreateOptions($schema);
            $this->driver->createRRDWithCustomConfig($rrdFile, $dataSources);

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

            // RRDtool doesn't support altering RRD files directly
            // We would need to create a new RRD file and migrate the data
            // For simplicity, we'll just update the schema in the registry
            $this->storeSchema($schema);

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

            // Check if the schema registry file exists
            if (! file_exists($this->schemaRegistryPath)) {
                throw new SchemaException('Schema registry does not exist');
            }

            // Read the schema registry
            $schemasJson = file_get_contents($this->schemaRegistryPath);
            if ($schemasJson === false) {
                throw new SchemaException('Failed to read schema registry');
            }

            $schemas = json_decode($schemasJson, true);
            if ($schemas === null) {
                throw new SchemaException('Invalid schema registry JSON');
            }

            // Find the schema for the measurement
            if (! isset($schemas[$measurement])) {
                throw new SchemaException("Schema for measurement '{$measurement}' not found");
            }

            $schemaData = $schemas[$measurement];

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

            // Get the current migrations
            $migrations = $this->getAppliedMigrations();

            // Add the new migration
            $migrations[] = $migrationName;

            // Write the updated migrations registry
            $migrationsJson = json_encode($migrations);
            $result = file_put_contents($this->migrationsRegistryPath, $migrationsJson);
            if ($result === false) {
                throw new SchemaException('Failed to write migrations registry');
            }

            // Update the applied migrations cache
            $this->appliedMigrationsCache = $migrations;

            return true;
        } catch (\Exception $e) {
            $this->logger->error("Error applying migration: {$e->getMessage()}");
            throw new SchemaException("Failed to apply migration: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Store a schema in the schema registry
     *
     * @param  MeasurementSchema  $schema  The schema to store
     *
     * @throws SchemaException If storing the schema fails
     */
    private function storeSchema(MeasurementSchema $schema): void
    {
        try {
            $measurementName = $schema->getName();
            $schemaData = $schema->toArray();

            // Check if the schema registry file exists
            if (! file_exists($this->schemaRegistryPath)) {
                // Create the schema registry if it doesn't exist
                $schemas = [];
            } else {
                // Read the existing schema registry
                $schemasJson = file_get_contents($this->schemaRegistryPath);
                if ($schemasJson === false) {
                    throw new SchemaException('Failed to read schema registry');
                }

                $schemas = json_decode($schemasJson, true);
                if ($schemas === null) {
                    throw new SchemaException('Invalid schema registry JSON');
                }
            }

            // Update the schema
            $schemas[$measurementName] = $schemaData;

            // Write the updated schema registry
            $schemasJson = json_encode($schemas);
            $this->ensureDirectoryFor($this->schemaRegistryPath, 'schema registry');
            $result = file_put_contents($this->schemaRegistryPath, $schemasJson);
            if ($result === false) {
                throw new SchemaException('Failed to write schema registry');
            }
        } catch (\Exception $e) {
            $this->logger->error("Error storing schema: {$e->getMessage()}");
            throw new SchemaException("Failed to store schema: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Create the migrations registry
     *
     * @throws SchemaException If creating the migrations registry fails
     */
    private function createMigrationsRegistry(): void
    {
        try {
            $this->logger->debug('Creating migrations registry');

            // Create an empty migrations registry
            $migrations = [];
            $migrationsJson = json_encode($migrations);
            $this->ensureDirectoryFor($this->migrationsRegistryPath, 'migrations registry');
            $result = file_put_contents($this->migrationsRegistryPath, $migrationsJson);
            if ($result === false) {
                throw new SchemaException('Failed to create migrations registry');
            }
        } catch (\Exception $e) {
            $this->logger->error("Error creating migrations registry: {$e->getMessage()}");
            throw new SchemaException("Failed to create migrations registry: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Build the create data source options for an RRD file based on a schema
     *
     * @param  MeasurementSchema  $schema  The schema to use
     * @return array<string> The data source definitions (DS:... strings)
     */
    private function buildCreateOptions(MeasurementSchema $schema): array
    {
        $dataSources = [];

        // Add data sources for each field
        foreach ($schema->getFields() as $fieldName => $fieldDefinition) {
            $type = 'GAUGE';
            $min = 'U';
            $max = 'U';

            // Map field types to RRDtool data source types
            switch ($fieldDefinition->getType()) {
                case 'integer':
                case 'float':
                    $type = 'GAUGE';
                    break;
                case 'counter':
                    $type = 'COUNTER';
                    break;
                case 'derive':
                    $type = 'DERIVE';
                    break;
                case 'absolute':
                    $type = 'ABSOLUTE';
                    break;
            }

            // Add the data source
            $dataSources[] = "DS:{$fieldName}:{$type}:120:{$min}:{$max}";
        }

        // Note: Archives (RRA) and step are intentionally not specified here to
        // leverage driver defaults and allow sanitization at the driver level.

        return $dataSources;
    }

    /**
     * Ensure the parent directory for a target file path exists.
     *
     * @param  string  $filePath  The full path to the target file whose directory must exist
     * @param  string  $context  A short context used in error messages (e.g., 'schema registry')
     *
     * @throws SchemaException If the directory cannot be created
     */
    private function ensureDirectoryFor(string $filePath, string $context): void
    {
        $dir = dirname($filePath);
        if (! is_dir($dir)) {
            if (! @mkdir($dir, 0777, true) && ! is_dir($dir)) {
                throw new SchemaException(sprintf('Failed to create %s directory: %s', $context, $dir));
            }
        }
    }
}

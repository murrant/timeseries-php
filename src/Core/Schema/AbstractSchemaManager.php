<?php

namespace TimeSeriesPhp\Core\Schema;

use Psr\Log\LoggerInterface;
use TimeSeriesPhp\Contracts\Schema\SchemaManagerInterface;
use TimeSeriesPhp\Exceptions\Schema\SchemaException;

/**
 * Abstract base class for schema managers
 */
abstract class AbstractSchemaManager implements SchemaManagerInterface
{
    /**
     * @var array<string, MeasurementSchema> Cache of measurement schemas
     */
    protected array $schemaCache = [];

    /**
     * @param LoggerInterface $logger Logger for recording operations
     */
    public function __construct(
        protected LoggerInterface $logger
    ) {
    }

    /**
     * {@inheritdoc}
     */
    public function createMeasurement(MeasurementSchema $schema): bool
    {
        $measurementName = $schema->getName();
        
        try {
            $this->logger->info("Creating measurement: {$measurementName}");
            
            if ($this->measurementExists($measurementName)) {
                throw new SchemaException("Measurement '{$measurementName}' already exists");
            }
            
            $result = $this->doCreateMeasurement($schema);
            
            if ($result) {
                $this->schemaCache[$measurementName] = $schema;
                $this->logger->info("Successfully created measurement: {$measurementName}");
            } else {
                $this->logger->error("Failed to create measurement: {$measurementName}");
            }
            
            return $result;
        } catch (SchemaException $e) {
            $this->logger->error("Error creating measurement '{$measurementName}': {$e->getMessage()}");
            throw $e;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function updateMeasurement(MeasurementSchema $schema): bool
    {
        $measurementName = $schema->getName();
        
        try {
            $this->logger->info("Updating measurement: {$measurementName}");
            
            if (!$this->measurementExists($measurementName)) {
                throw new SchemaException("Measurement '{$measurementName}' does not exist");
            }
            
            $result = $this->doUpdateMeasurement($schema);
            
            if ($result) {
                $this->schemaCache[$measurementName] = $schema;
                $this->logger->info("Successfully updated measurement: {$measurementName}");
            } else {
                $this->logger->error("Failed to update measurement: {$measurementName}");
            }
            
            return $result;
        } catch (SchemaException $e) {
            $this->logger->error("Error updating measurement '{$measurementName}': {$e->getMessage()}");
            throw $e;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getMeasurementSchema(string $measurement): MeasurementSchema
    {
        try {
            $this->logger->debug("Getting schema for measurement: {$measurement}");
            
            if (isset($this->schemaCache[$measurement])) {
                return $this->schemaCache[$measurement];
            }
            
            if (!$this->measurementExists($measurement)) {
                throw new SchemaException("Measurement '{$measurement}' does not exist");
            }
            
            $schema = $this->doGetMeasurementSchema($measurement);
            $this->schemaCache[$measurement] = $schema;
            
            return $schema;
        } catch (SchemaException $e) {
            $this->logger->error("Error getting schema for measurement '{$measurement}': {$e->getMessage()}");
            throw $e;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function validateSchema(string $measurement, array $data): SchemaValidationResult
    {
        try {
            $this->logger->debug("Validating data against schema for measurement: {$measurement}");
            
            $schema = $this->getMeasurementSchema($measurement);
            return $this->doValidateSchema($schema, $data);
        } catch (SchemaException $e) {
            $this->logger->error("Error validating schema for measurement '{$measurement}': {$e->getMessage()}");
            throw $e;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function applyMigration(string $migrationName): bool
    {
        try {
            $this->logger->info("Applying migration: {$migrationName}");
            
            $appliedMigrations = $this->getAppliedMigrations();
            if (in_array($migrationName, $appliedMigrations)) {
                $this->logger->info("Migration '{$migrationName}' has already been applied");
                return true;
            }
            
            $result = $this->doApplyMigration($migrationName);
            
            if ($result) {
                $this->logger->info("Successfully applied migration: {$migrationName}");
            } else {
                $this->logger->error("Failed to apply migration: {$migrationName}");
            }
            
            return $result;
        } catch (SchemaException $e) {
            $this->logger->error("Error applying migration '{$migrationName}': {$e->getMessage()}");
            throw $e;
        }
    }

    /**
     * Perform the actual creation of a measurement
     *
     * @param MeasurementSchema $schema The schema definition for the measurement
     * @return bool True if the measurement was created successfully
     *
     * @throws SchemaException If measurement creation fails
     */
    abstract protected function doCreateMeasurement(MeasurementSchema $schema): bool;

    /**
     * Perform the actual update of a measurement
     *
     * @param MeasurementSchema $schema The updated schema definition
     * @return bool True if the measurement was updated successfully
     *
     * @throws SchemaException If measurement update fails
     */
    abstract protected function doUpdateMeasurement(MeasurementSchema $schema): bool;

    /**
     * Perform the actual retrieval of a measurement schema
     *
     * @param string $measurement The name of the measurement
     * @return MeasurementSchema The measurement schema
     *
     * @throws SchemaException If getting the schema fails
     */
    abstract protected function doGetMeasurementSchema(string $measurement): MeasurementSchema;

    /**
     * Perform the actual validation of data against a schema
     *
     * @param MeasurementSchema $schema The schema to validate against
     * @param array $data The data to validate
     * @return SchemaValidationResult The validation result
     */
    protected function doValidateSchema(MeasurementSchema $schema, array $data): SchemaValidationResult
    {
        $result = new SchemaValidationResult();
        
        // Validate fields
        foreach ($schema->getFields() as $fieldName => $fieldDefinition) {
            $fieldValue = $data[$fieldName] ?? null;
            
            if (!$fieldDefinition->validateValue($fieldValue)) {
                $type = $fieldDefinition->getType();
                $result->addError($fieldName, "Invalid value for field '{$fieldName}' of type '{$type}'");
            }
        }
        
        // Validate tags
        foreach ($schema->getTags() as $tagName => $tagDefinition) {
            $tagValue = $data[$tagName] ?? null;
            
            if (!$tagDefinition->validateValue($tagValue)) {
                $result->addError($tagName, "Invalid value for tag '{$tagName}'");
            }
        }
        
        // Check for required fields and tags
        foreach ($schema->getFields() as $fieldName => $fieldDefinition) {
            if ($fieldDefinition->isRequired() && !isset($data[$fieldName])) {
                $result->addError($fieldName, "Required field '{$fieldName}' is missing");
            }
        }
        
        foreach ($schema->getTags() as $tagName => $tagDefinition) {
            if ($tagDefinition->isRequired() && !isset($data[$tagName])) {
                $result->addError($tagName, "Required tag '{$tagName}' is missing");
            }
        }
        
        return $result;
    }

    /**
     * Perform the actual application of a migration
     *
     * @param string $migrationName The name of the migration to apply
     * @return bool True if the migration was applied successfully
     *
     * @throws SchemaException If applying the migration fails
     */
    abstract protected function doApplyMigration(string $migrationName): bool;
}
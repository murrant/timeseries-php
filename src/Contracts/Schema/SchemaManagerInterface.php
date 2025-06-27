<?php

namespace TimeSeriesPhp\Contracts\Schema;

use TimeSeriesPhp\Core\Schema\MeasurementSchema;
use TimeSeriesPhp\Core\Schema\SchemaValidationResult;
use TimeSeriesPhp\Exceptions\Schema\SchemaException;

/**
 * Interface for managing database schemas and migrations
 */
interface SchemaManagerInterface
{
    /**
     * Create a new measurement with the specified schema
     *
     * @param  MeasurementSchema  $schema  The schema definition for the measurement
     * @return bool True if the measurement was created successfully
     *
     * @throws SchemaException If measurement creation fails
     */
    public function createMeasurement(MeasurementSchema $schema): bool;

    /**
     * Update an existing measurement schema
     *
     * @param  MeasurementSchema  $schema  The updated schema definition
     * @return bool True if the measurement was updated successfully
     *
     * @throws SchemaException If measurement update fails
     */
    public function updateMeasurement(MeasurementSchema $schema): bool;

    /**
     * Get the schema for an existing measurement
     *
     * @param  string  $measurement  The name of the measurement
     * @return MeasurementSchema The measurement schema
     *
     * @throws SchemaException If getting the schema fails
     */
    public function getMeasurementSchema(string $measurement): MeasurementSchema;

    /**
     * List all measurements in the database
     *
     * @return string[] Array of measurement names
     *
     * @throws SchemaException If listing measurements fails
     */
    public function listMeasurements(): array;

    /**
     * Validate data against a measurement schema
     *
     * @param  string  $measurement  The name of the measurement
     * @param  array  $data  The data to validate
     * @return SchemaValidationResult The validation result
     *
     * @throws SchemaException If validation fails
     */
    public function validateSchema(string $measurement, array $data): SchemaValidationResult;

    /**
     * Apply a migration to update the database schema
     *
     * @param  string  $migrationName  The name of the migration to apply
     * @return bool True if the migration was applied successfully
     *
     * @throws SchemaException If applying the migration fails
     */
    public function applyMigration(string $migrationName): bool;

    /**
     * Get a list of all applied migrations
     *
     * @return string[] Array of applied migration names
     *
     * @throws SchemaException If listing migrations fails
     */
    public function getAppliedMigrations(): array;

    /**
     * Check if a measurement exists
     *
     * @param  string  $measurement  The name of the measurement to check
     * @return bool True if the measurement exists
     *
     * @throws SchemaException If checking measurement existence fails
     */
    public function measurementExists(string $measurement): bool;
}

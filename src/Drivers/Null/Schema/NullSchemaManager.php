<?php

namespace TimeSeriesPhp\Drivers\Null\Schema;

use Psr\Log\LoggerInterface;
use TimeSeriesPhp\Core\Schema\AbstractSchemaManager;
use TimeSeriesPhp\Core\Schema\MeasurementSchema;
use TimeSeriesPhp\Drivers\Null\NullDriver;
use TimeSeriesPhp\Exceptions\Schema\SchemaException;

/**
 * Schema manager for Null driver
 * 
 * This is a no-op implementation that simulates schema management operations
 * without actually doing anything.
 */
class NullSchemaManager extends AbstractSchemaManager
{
    /**
     * @var array<string, MeasurementSchema> Cache of measurement schemas
     */
    private array $schemas = [];

    /**
     * @var array<string> Cache of applied migrations
     */
    private array $appliedMigrations = [];

    /**
     * @var array<string> List of measurements
     */
    private array $measurements = [];

    /**
     * @param NullDriver $driver The Null driver
     * @param LoggerInterface $logger Logger for recording operations
     */
    public function __construct(
        private readonly NullDriver $driver,
        LoggerInterface $logger
    ) {
        parent::__construct($logger);
    }

    /**
     * {@inheritdoc}
     */
    public function listMeasurements(): array
    {
        $this->logger->debug('Listing measurements');
        return $this->measurements;
    }

    /**
     * {@inheritdoc}
     */
    public function measurementExists(string $measurement): bool
    {
        $this->logger->debug("Checking if measurement exists: {$measurement}");
        return in_array($measurement, $this->measurements);
    }

    /**
     * {@inheritdoc}
     */
    public function getAppliedMigrations(): array
    {
        $this->logger->debug('Getting applied migrations');
        return $this->appliedMigrations;
    }

    /**
     * {@inheritdoc}
     */
    protected function doCreateMeasurement(MeasurementSchema $schema): bool
    {
        $measurementName = $schema->getName();
        $this->logger->debug("Creating measurement: {$measurementName}");
        
        $this->schemas[$measurementName] = $schema;
        $this->measurements[] = $measurementName;
        
        return true;
    }

    /**
     * {@inheritdoc}
     */
    protected function doUpdateMeasurement(MeasurementSchema $schema): bool
    {
        $measurementName = $schema->getName();
        $this->logger->debug("Updating measurement: {$measurementName}");
        
        $this->schemas[$measurementName] = $schema;
        
        return true;
    }

    /**
     * {@inheritdoc}
     */
    protected function doGetMeasurementSchema(string $measurement): MeasurementSchema
    {
        $this->logger->debug("Getting schema for measurement: {$measurement}");
        
        if (!isset($this->schemas[$measurement])) {
            throw new SchemaException("Schema for measurement '{$measurement}' not found");
        }
        
        return $this->schemas[$measurement];
    }

    /**
     * {@inheritdoc}
     */
    protected function doApplyMigration(string $migrationName): bool
    {
        $this->logger->debug("Applying migration: {$migrationName}");
        
        $this->appliedMigrations[] = $migrationName;
        
        return true;
    }
}
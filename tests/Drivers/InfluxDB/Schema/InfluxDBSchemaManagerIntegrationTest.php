<?php

namespace TimeSeriesPhp\Tests\Drivers\InfluxDB\Schema;

use PHPUnit\Framework\TestCase;
use TimeSeriesPhp\Core\Schema\FieldDefinition;
use TimeSeriesPhp\Core\Schema\MeasurementSchema;
use TimeSeriesPhp\Core\Schema\TagDefinition;
use TimeSeriesPhp\Drivers\InfluxDB\Schema\InfluxDBSchemaManager;
use TimeSeriesPhp\TSDB;

/**
 * Integration test for InfluxDBSchemaManager that assumes InfluxDB is available
 * and can be connected to with the provided configuration.
 *
 * @group integration
 */
class InfluxDBSchemaManagerIntegrationTest extends TestCase
{
    private InfluxDBSchemaManager $schemaManager;
    private TSDB $tsdb;
    private string $testBucket = 'test_integration';
    private string $testMeasurement = 'test_schema_manager';

    protected function setUp(): void
    {
        // Skip test if curl extension is not available
        if (!extension_loaded('curl')) {
            $this->markTestSkipped('curl extension is not available');
        }

        // Try to connect to InfluxDB
        $influxUrl = getenv('INFLUXDB_URL') ?: 'http://localhost:8086';
        $influxToken = getenv('INFLUXDB_TOKEN') ?: 'my-token';
        $influxOrg = getenv('INFLUXDB_ORG') ?: 'my-org';
        $influxBucket = getenv('INFLUXDB_BUCKET') ?: $this->testBucket;

        // Create configuration array
        $config = [
            'url' => $influxUrl,
            'token' => $influxToken,
            'org' => $influxOrg,
            'bucket' => $influxBucket,
            'timeout' => 5, // Short timeout for testing
            'verify_ssl' => false, // Don't verify SSL for testing
            'debug' => false,
        ];

        // Initialize driver using TSDB
        try {
            $this->tsdb = TSDB::start('influxdb', $config);
            $driver = $this->tsdb->getDriver();

            if (!$driver->isConnected()) {
                $this->markTestSkipped('Could not connect to InfluxDB at ' . $influxUrl);
            }

            // Create test bucket if it doesn't exist
            try {
                $driver->createDatabase($this->testBucket);
            } catch (\Exception $e) {
                // Bucket might already exist, that's fine
            }

            // Get the schema manager
            $this->schemaManager = $this->tsdb->getSchemaManager();
        } catch (\Exception $e) {
            $this->markTestSkipped('Could not connect to InfluxDB: ' . $e->getMessage());
        }
    }

    protected function tearDown(): void
    {
        // Close the driver connection
        if (isset($this->tsdb) && $this->tsdb->getDriver()->isConnected()) {
            $this->tsdb->close();
        }
    }

    public function test_create_measurement(): void
    {
        // Create a schema
        $schema = new MeasurementSchema($this->testMeasurement);
        $schema->addField('value', new FieldDefinition('float', true));
        $schema->addTag('host', new TagDefinition(true));
        $schema->addTag('region', new TagDefinition(false));

        // Create the measurement
        $result = $this->schemaManager->createMeasurement($schema);
        $this->assertTrue($result);

        // Verify the measurement exists
        $exists = $this->schemaManager->measurementExists($this->testMeasurement);
        $this->assertTrue($exists);
    }

    public function test_get_measurement_schema(): void
    {
        // Create a schema if it doesn't exist
        if (!$this->schemaManager->measurementExists($this->testMeasurement)) {
            $this->test_create_measurement();
        }

        // Get the schema
        $schema = $this->schemaManager->getMeasurementSchema($this->testMeasurement);
        
        // Verify the schema
        $this->assertEquals($this->testMeasurement, $schema->getName());
        $this->assertTrue($schema->hasField('value'));
        $this->assertTrue($schema->hasTag('host'));
        $this->assertTrue($schema->hasTag('region'));
    }

    public function test_update_measurement(): void
    {
        // Create a schema if it doesn't exist
        if (!$this->schemaManager->measurementExists($this->testMeasurement)) {
            $this->test_create_measurement();
        }

        // Get the current schema
        $schema = $this->schemaManager->getMeasurementSchema($this->testMeasurement);
        
        // Update the schema
        $schema->addField('cpu', new FieldDefinition('float', false));
        $schema->addTag('datacenter', new TagDefinition(false));
        
        // Update the measurement
        $result = $this->schemaManager->updateMeasurement($schema);
        $this->assertTrue($result);
        
        // Get the updated schema
        $updatedSchema = $this->schemaManager->getMeasurementSchema($this->testMeasurement);
        
        // Verify the schema was updated
        $this->assertTrue($updatedSchema->hasField('cpu'));
        $this->assertTrue($updatedSchema->hasTag('datacenter'));
    }

    public function test_list_measurements(): void
    {
        // Create a schema if it doesn't exist
        if (!$this->schemaManager->measurementExists($this->testMeasurement)) {
            $this->test_create_measurement();
        }

        // List measurements
        $measurements = $this->schemaManager->listMeasurements();
        
        // Verify the test measurement is in the list
        $this->assertContains($this->testMeasurement, $measurements);
    }

    public function test_validate_schema_valid(): void
    {
        // Create a schema if it doesn't exist
        if (!$this->schemaManager->measurementExists($this->testMeasurement)) {
            $this->test_create_measurement();
        }

        // Validate valid data
        $result = $this->schemaManager->validateSchema($this->testMeasurement, [
            'value' => 42.5,
            'host' => 'test-server',
            'region' => 'us-west',
        ]);
        
        // Verify the validation result
        $this->assertTrue($result->isValid());
        $this->assertEmpty($result->getErrors());
    }

    public function test_validate_schema_invalid(): void
    {
        // Create a schema if it doesn't exist
        if (!$this->schemaManager->measurementExists($this->testMeasurement)) {
            $this->test_create_measurement();
        }

        // Validate invalid data
        $result = $this->schemaManager->validateSchema($this->testMeasurement, [
            'value' => 'not a float',
            // Missing required 'host' tag
        ]);
        
        // Verify the validation result
        $this->assertFalse($result->isValid());
        $this->assertNotEmpty($result->getErrors());
    }

    public function test_apply_migration(): void
    {
        // Apply a test migration
        $migrationName = 'test_migration_' . uniqid();
        $result = $this->schemaManager->applyMigration($migrationName);
        $this->assertTrue($result);
        
        // Verify the migration was applied
        $migrations = $this->schemaManager->getAppliedMigrations();
        $this->assertContains($migrationName, $migrations);
    }
}
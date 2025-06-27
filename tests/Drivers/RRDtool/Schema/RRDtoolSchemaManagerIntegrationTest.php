<?php

namespace TimeSeriesPhp\Tests\Drivers\RRDtool\Schema;

use PHPUnit\Framework\TestCase;
use TimeSeriesPhp\Core\Schema\FieldDefinition;
use TimeSeriesPhp\Core\Schema\MeasurementSchema;
use TimeSeriesPhp\Core\Schema\TagDefinition;
use TimeSeriesPhp\Drivers\RRDtool\Schema\RRDtoolSchemaManager;
use TimeSeriesPhp\TSDB;

/**
 * Integration test for RRDtoolSchemaManager that assumes RRDtool is available
 * and can be connected to with the provided configuration.
 *
 * @group integration
 */
class RRDtoolSchemaManagerIntegrationTest extends TestCase
{
    private RRDtoolSchemaManager $schemaManager;
    private TSDB $tsdb;
    private string $testMeasurement = 'test_schema_manager';
    private string $dataDir;

    protected function setUp(): void
    {
        // Skip test if rrdtool is not available
        exec('which rrdtool', $output, $returnVar);
        if ($returnVar !== 0) {
            $this->markTestSkipped('rrdtool is not available');
        }

        // Create a temporary data directory
        $this->dataDir = sys_get_temp_dir() . '/rrdtool_test_' . uniqid();
        if (!is_dir($this->dataDir)) {
            mkdir($this->dataDir, 0777, true);
        }

        // Create configuration array
        $config = [
            'binary' => 'rrdtool',
            'data_dir' => $this->dataDir,
        ];

        // Initialize driver using TSDB
        try {
            $this->tsdb = TSDB::start('rrdtool', $config);
            $driver = $this->tsdb->getDriver();

            // Get the schema manager
            $this->schemaManager = $this->tsdb->getSchemaManager();
        } catch (\Exception $e) {
            $this->markTestSkipped('Could not initialize RRDtool driver: ' . $e->getMessage());
        }
    }

    protected function tearDown(): void
    {
        // Close the driver connection
        if (isset($this->tsdb)) {
            $this->tsdb->close();
        }

        // Clean up the temporary data directory
        if (isset($this->dataDir) && is_dir($this->dataDir)) {
            $files = glob($this->dataDir . '/*');
            foreach ($files as $file) {
                unlink($file);
            }
            rmdir($this->dataDir);
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

        // Verify the RRD file was created
        $rrdFile = $this->dataDir . '/' . $this->testMeasurement . '.rrd';
        $this->assertFileExists($rrdFile);
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
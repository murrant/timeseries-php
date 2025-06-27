<?php

namespace TimeSeriesPhp\Tests\Drivers\InfluxDB\Schema;

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use TimeSeriesPhp\Core\Data\QueryResult;
use TimeSeriesPhp\Core\Data\Series;
use TimeSeriesPhp\Core\Schema\FieldDefinition;
use TimeSeriesPhp\Core\Schema\MeasurementSchema;
use TimeSeriesPhp\Core\Schema\TagDefinition;
use TimeSeriesPhp\Drivers\InfluxDB\InfluxDBDriver;
use TimeSeriesPhp\Drivers\InfluxDB\InfluxDBRawQuery;
use TimeSeriesPhp\Drivers\InfluxDB\Schema\InfluxDBSchemaManager;

class InfluxDBSchemaManagerTest extends TestCase
{
    private InfluxDBDriver $mockDriver;
    private LoggerInterface $mockLogger;
    private InfluxDBSchemaManager $schemaManager;

    protected function setUp(): void
    {
        $this->mockDriver = $this->createMock(InfluxDBDriver::class);
        $this->mockLogger = $this->createMock(LoggerInterface::class);
        $this->schemaManager = new InfluxDBSchemaManager($this->mockDriver, $this->mockLogger);
    }

    public function test_list_measurements(): void
    {
        // Create a mock query result with measurements
        $series = new Series('measurements', ['name'], [
            ['cpu'],
            ['memory'],
            ['disk'],
        ]);
        $queryResult = new QueryResult();
        $queryResult->addSeries($series);

        // Set up the mock driver to return the query result
        $this->mockDriver->expects($this->once())
            ->method('rawQuery')
            ->with($this->callback(function (InfluxDBRawQuery $query) {
                return $query->getRawQuery() === 'SHOW MEASUREMENTS';
            }))
            ->willReturn($queryResult);

        // Call the method and assert the result
        $measurements = $this->schemaManager->listMeasurements();
        $this->assertEquals(['cpu', 'memory', 'disk'], $measurements);
    }

    public function test_measurement_exists_true(): void
    {
        // Create a mock query result with the measurement
        $series = new Series('measurements', ['name'], [
            ['cpu'],
        ]);
        $queryResult = new QueryResult();
        $queryResult->addSeries($series);

        // Set up the mock driver to return the query result
        $this->mockDriver->expects($this->once())
            ->method('rawQuery')
            ->with($this->callback(function (InfluxDBRawQuery $query) {
                return $query->getRawQuery() === "SHOW MEASUREMENTS WHERE name = 'cpu'";
            }))
            ->willReturn($queryResult);

        // Call the method and assert the result
        $exists = $this->schemaManager->measurementExists('cpu');
        $this->assertTrue($exists);
    }

    public function test_measurement_exists_false(): void
    {
        // Create a mock query result with no measurements
        $queryResult = new QueryResult();

        // Set up the mock driver to return the query result
        $this->mockDriver->expects($this->once())
            ->method('rawQuery')
            ->with($this->callback(function (InfluxDBRawQuery $query) {
                return $query->getRawQuery() === "SHOW MEASUREMENTS WHERE name = 'nonexistent'";
            }))
            ->willReturn($queryResult);

        // Call the method and assert the result
        $exists = $this->schemaManager->measurementExists('nonexistent');
        $this->assertFalse($exists);
    }

    public function test_create_measurement(): void
    {
        // Create a schema
        $schema = new MeasurementSchema('cpu');
        $schema->addField('usage', new FieldDefinition('float', true));
        $schema->addTag('host', new TagDefinition(true));

        // Set up the mock driver to handle both the existence check and the insert
        $this->mockDriver->expects($this->exactly(2))
            ->method('rawQuery')
            ->withConsecutive(
                [$this->callback(function (InfluxDBRawQuery $query) {
                    return $query->getRawQuery() === "SHOW MEASUREMENTS WHERE name = 'cpu'";
                })],
                [$this->callback(function (InfluxDBRawQuery $query) {
                    return strpos($query->getRawQuery(), 'INSERT schema_registry,measurement_name="cpu"') === 0;
                })]
            )
            ->willReturnOnConsecutiveCalls(
                new QueryResult(), // Empty result for measurementExists
                new QueryResult()  // Result for the insert
            );

        // Call the method and assert the result
        $result = $this->schemaManager->createMeasurement($schema);
        $this->assertTrue($result);
    }

    public function test_update_measurement(): void
    {
        // Create a schema
        $schema = new MeasurementSchema('cpu');
        $schema->addField('usage', new FieldDefinition('float', true));
        $schema->addTag('host', new TagDefinition(true));

        // Create a mock query result with the measurement
        $series = new Series('measurements', ['name'], [
            ['cpu'],
        ]);
        $existsQueryResult = new QueryResult();
        $existsQueryResult->addSeries($series);

        // Set up the mock driver to handle both the existence check and the update
        $this->mockDriver->expects($this->exactly(2))
            ->method('rawQuery')
            ->withConsecutive(
                [$this->callback(function (InfluxDBRawQuery $query) {
                    return $query->getRawQuery() === "SHOW MEASUREMENTS WHERE name = 'cpu'";
                })],
                [$this->callback(function (InfluxDBRawQuery $query) {
                    return strpos($query->getRawQuery(), 'INSERT schema_registry,measurement_name="cpu"') === 0;
                })]
            )
            ->willReturnOnConsecutiveCalls(
                $existsQueryResult, // Result with the measurement for measurementExists
                new QueryResult()   // Result for the update
            );

        // Call the method and assert the result
        $result = $this->schemaManager->updateMeasurement($schema);
        $this->assertTrue($result);
    }

    public function test_get_measurement_schema(): void
    {
        // Create a schema
        $schema = new MeasurementSchema('cpu');
        $schema->addField('usage', new FieldDefinition('float', true));
        $schema->addTag('host', new TagDefinition(true));
        $schemaJson = json_encode($schema->toArray());

        // Create a mock query result with the schema
        $series = new Series('schema_registry', ['time', 'schema'], [
            ['2023-01-01T00:00:00Z', $schemaJson],
        ]);
        $schemaQueryResult = new QueryResult();
        $schemaQueryResult->addSeries($series);

        // Create a mock query result for schema_registry existence
        $registrySeries = new Series('measurements', ['name'], [
            ['schema_registry'],
        ]);
        $registryQueryResult = new QueryResult();
        $registryQueryResult->addSeries($registrySeries);

        // Create a mock query result for cpu measurement existence
        $cpuSeries = new Series('measurements', ['name'], [
            ['cpu'],
        ]);
        $cpuQueryResult = new QueryResult();
        $cpuQueryResult->addSeries($cpuSeries);

        // Set up the mock driver to handle all the necessary queries
        $this->mockDriver->expects($this->exactly(3))
            ->method('rawQuery')
            ->withConsecutive(
                [$this->callback(function (InfluxDBRawQuery $query) {
                    return $query->getRawQuery() === "SHOW MEASUREMENTS WHERE name = 'cpu'";
                })],
                [$this->callback(function (InfluxDBRawQuery $query) {
                    return $query->getRawQuery() === "SHOW MEASUREMENTS WHERE name = 'schema_registry'";
                })],
                [$this->callback(function (InfluxDBRawQuery $query) {
                    return strpos($query->getRawQuery(), "SELECT schema FROM schema_registry WHERE measurement_name = 'cpu'") === 0;
                })]
            )
            ->willReturnOnConsecutiveCalls(
                $cpuQueryResult,
                $registryQueryResult,
                $schemaQueryResult
            );

        // Call the method and assert the result
        $result = $this->schemaManager->getMeasurementSchema('cpu');
        $this->assertEquals('cpu', $result->getName());
        $this->assertTrue($result->hasField('usage'));
        $this->assertTrue($result->hasTag('host'));
    }

    public function test_validate_schema_valid(): void
    {
        // Create a schema
        $schema = new MeasurementSchema('cpu');
        $schema->addField('usage', new FieldDefinition('float', true));
        $schema->addTag('host', new TagDefinition(true));
        $schemaJson = json_encode($schema->toArray());

        // Create a mock query result with the schema
        $series = new Series('schema_registry', ['time', 'schema'], [
            ['2023-01-01T00:00:00Z', $schemaJson],
        ]);
        $schemaQueryResult = new QueryResult();
        $schemaQueryResult->addSeries($series);

        // Create a mock query result for schema_registry existence
        $registrySeries = new Series('measurements', ['name'], [
            ['schema_registry'],
        ]);
        $registryQueryResult = new QueryResult();
        $registryQueryResult->addSeries($registrySeries);

        // Create a mock query result for cpu measurement existence
        $cpuSeries = new Series('measurements', ['name'], [
            ['cpu'],
        ]);
        $cpuQueryResult = new QueryResult();
        $cpuQueryResult->addSeries($cpuSeries);

        // Set up the mock driver to handle all the necessary queries
        $this->mockDriver->expects($this->exactly(3))
            ->method('rawQuery')
            ->withConsecutive(
                [$this->callback(function (InfluxDBRawQuery $query) {
                    return $query->getRawQuery() === "SHOW MEASUREMENTS WHERE name = 'cpu'";
                })],
                [$this->callback(function (InfluxDBRawQuery $query) {
                    return $query->getRawQuery() === "SHOW MEASUREMENTS WHERE name = 'schema_registry'";
                })],
                [$this->callback(function (InfluxDBRawQuery $query) {
                    return strpos($query->getRawQuery(), "SELECT schema FROM schema_registry WHERE measurement_name = 'cpu'") === 0;
                })]
            )
            ->willReturnOnConsecutiveCalls(
                $cpuQueryResult,
                $registryQueryResult,
                $schemaQueryResult
            );

        // Call the method and assert the result
        $result = $this->schemaManager->validateSchema('cpu', [
            'usage' => 0.5,
            'host' => 'server1',
        ]);
        $this->assertTrue($result->isValid());
    }

    public function test_validate_schema_invalid(): void
    {
        // Create a schema
        $schema = new MeasurementSchema('cpu');
        $schema->addField('usage', new FieldDefinition('float', true));
        $schema->addTag('host', new TagDefinition(true));
        $schemaJson = json_encode($schema->toArray());

        // Create a mock query result with the schema
        $series = new Series('schema_registry', ['time', 'schema'], [
            ['2023-01-01T00:00:00Z', $schemaJson],
        ]);
        $schemaQueryResult = new QueryResult();
        $schemaQueryResult->addSeries($series);

        // Create a mock query result for schema_registry existence
        $registrySeries = new Series('measurements', ['name'], [
            ['schema_registry'],
        ]);
        $registryQueryResult = new QueryResult();
        $registryQueryResult->addSeries($registrySeries);

        // Create a mock query result for cpu measurement existence
        $cpuSeries = new Series('measurements', ['name'], [
            ['cpu'],
        ]);
        $cpuQueryResult = new QueryResult();
        $cpuQueryResult->addSeries($cpuSeries);

        // Set up the mock driver to handle all the necessary queries
        $this->mockDriver->expects($this->exactly(3))
            ->method('rawQuery')
            ->withConsecutive(
                [$this->callback(function (InfluxDBRawQuery $query) {
                    return $query->getRawQuery() === "SHOW MEASUREMENTS WHERE name = 'cpu'";
                })],
                [$this->callback(function (InfluxDBRawQuery $query) {
                    return $query->getRawQuery() === "SHOW MEASUREMENTS WHERE name = 'schema_registry'";
                })],
                [$this->callback(function (InfluxDBRawQuery $query) {
                    return strpos($query->getRawQuery(), "SELECT schema FROM schema_registry WHERE measurement_name = 'cpu'") === 0;
                })]
            )
            ->willReturnOnConsecutiveCalls(
                $cpuQueryResult,
                $registryQueryResult,
                $schemaQueryResult
            );

        // Call the method and assert the result
        $result = $this->schemaManager->validateSchema('cpu', [
            'usage' => 'not a float',
            'host' => 123, // Not a string
        ]);
        $this->assertFalse($result->isValid());
        $this->assertTrue($result->hasError('usage'));
        $this->assertTrue($result->hasError('host'));
    }
}

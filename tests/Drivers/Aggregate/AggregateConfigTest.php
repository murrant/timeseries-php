<?php

namespace TimeSeriesPhp\Tests\Drivers\Aggregate;

use PHPUnit\Framework\TestCase;
use TimeSeriesPhp\Drivers\Aggregate\AggregateConfig;
use TimeSeriesPhp\Exceptions\Config\ConfigurationException;

class AggregateConfigTest extends TestCase
{
    public function test_default_values(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('At least one write database must be configured');

        // Should throw exception because write_databases is required and empty by default
        new AggregateConfig;
    }

    public function test_custom_values(): void
    {
        $write_databases = [
            [
                'driver' => 'influxdb',
                'url' => 'http://influxdb1.example.com:8086',
                'token' => 'token1',
                'org' => 'org1',
                'bucket' => 'bucket1',
            ],
            [
                'driver' => 'influxdb',
                'url' => 'http://influxdb2.example.com:8086',
                'token' => 'token2',
                'org' => 'org2',
                'bucket' => 'bucket2',
            ],
        ];

        $read_database = [
            'driver' => 'influxdb',
            'url' => 'http://influxdb-read.example.com:8086',
            'token' => 'token-read',
            'org' => 'org-read',
            'bucket' => 'bucket-read',
        ];

        $config = new AggregateConfig($write_databases, $read_database);

        $writeDatabases = $config->getWriteDatabases();
        $this->assertCount(2, $writeDatabases);
        $this->assertEquals('influxdb', $writeDatabases[0]['driver']);
        $this->assertEquals('http://influxdb1.example.com:8086', $writeDatabases[0]['url']);
        $this->assertEquals('token1', $writeDatabases[0]['token']);
        $this->assertEquals('org1', $writeDatabases[0]['org']);
        $this->assertEquals('bucket1', $writeDatabases[0]['bucket']);

        $this->assertEquals('influxdb', $writeDatabases[1]['driver']);
        $this->assertEquals('http://influxdb2.example.com:8086', $writeDatabases[1]['url']);
        $this->assertEquals('token2', $writeDatabases[1]['token']);
        $this->assertEquals('org2', $writeDatabases[1]['org']);
        $this->assertEquals('bucket2', $writeDatabases[1]['bucket']);

        $readDatabase = $config->getReadDatabase();
        $this->assertNotNull($readDatabase);
        $this->assertEquals('influxdb', $readDatabase['driver']);
        $this->assertEquals('http://influxdb-read.example.com:8086', $readDatabase['url']);
        $this->assertEquals('token-read', $readDatabase['token']);
        $this->assertEquals('org-read', $readDatabase['org']);
        $this->assertEquals('bucket-read', $readDatabase['bucket']);
    }

    public function test_missing_driver_in_write_database(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('Driver not specified for write database at index 0');

        $write_databases = [
            [
                // Missing driver
                'url' => 'http://influxdb1.example.com:8086',
                'token' => 'token1',
                'org' => 'org1',
                'bucket' => 'bucket1',
            ],
        ];

        new AggregateConfig($write_databases);
    }

    public function test_missing_driver_in_read_database(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('Driver not specified for read database');

        $write_databases = [
            [
                'driver' => 'influxdb',
                'url' => 'http://influxdb1.example.com:8086',
                'token' => 'token1',
                'org' => 'org1',
                'bucket' => 'bucket1',
            ],
        ];

        $read_database = [
            // Missing driver
            'url' => 'http://influxdb-read.example.com:8086',
            'token' => 'token-read',
            'org' => 'org-read',
            'bucket' => 'bucket-read',
        ];

        new AggregateConfig($write_databases, $read_database);
    }

    public function test_get_write_databases(): void
    {
        $write_databases = [
            [
                'driver' => 'influxdb',
                'url' => 'http://influxdb1.example.com:8086',
            ],
        ];

        $config = new AggregateConfig($write_databases);

        $writeDatabases = $config->getWriteDatabases();
        $this->assertCount(1, $writeDatabases);
        $this->assertEquals('influxdb', $writeDatabases[0]['driver']);
        $this->assertEquals('http://influxdb1.example.com:8086', $writeDatabases[0]['url']);
    }

    public function test_get_read_database_fallback(): void
    {
        $write_databases = [
            [
                'driver' => 'influxdb',
                'url' => 'http://influxdb1.example.com:8086',
                'token' => 'token1',
            ],
        ];
        // No read_database specified, should fall back to first write database

        $config = new AggregateConfig($write_databases);

        $readDatabase = $config->getReadDatabase();
        $this->assertNotNull($readDatabase);
        $this->assertEquals('influxdb', $readDatabase['driver']);
        $this->assertEquals('http://influxdb1.example.com:8086', $readDatabase['url']);
        $this->assertEquals('token1', $readDatabase['token']);
    }

    public function test_add_write_database(): void
    {
        $write_databases = [
            [
                'driver' => 'influxdb',
                'url' => 'http://influxdb1.example.com:8086',
            ],
        ];

        $config = new AggregateConfig($write_databases);

        $newConfig = $config->addWriteDatabase([
            'driver' => 'prometheus',
            'url' => 'http://prometheus.example.com:9090',
        ]);

        // Check that the original config is unchanged
        $writeDatabases = $config->getWriteDatabases();
        $this->assertCount(1, $writeDatabases);
        $this->assertEquals('influxdb', $writeDatabases[0]['driver']);
        $this->assertEquals('http://influxdb1.example.com:8086', $writeDatabases[0]['url']);

        // Check that the new config has both databases
        $newWriteDatabases = $newConfig->getWriteDatabases();
        $this->assertCount(2, $newWriteDatabases);
        $this->assertEquals('influxdb', $newWriteDatabases[0]['driver']);
        $this->assertEquals('http://influxdb1.example.com:8086', $newWriteDatabases[0]['url']);
        $this->assertEquals('prometheus', $newWriteDatabases[1]['driver']);
        $this->assertEquals('http://prometheus.example.com:9090', $newWriteDatabases[1]['url']);
    }

    public function test_add_write_database_missing_driver(): void
    {
        $write_databases = [
            [
                'driver' => 'influxdb',
                'url' => 'http://influxdb1.example.com:8086',
            ],
        ];

        $config = new AggregateConfig($write_databases);

        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('Driver not specified for write database');

        $config->addWriteDatabase([
            // Missing driver
            'url' => 'http://prometheus.example.com:9090',
        ]);
    }

    public function test_set_read_database(): void
    {
        $write_databases = [
            [
                'driver' => 'influxdb',
                'url' => 'http://influxdb1.example.com:8086',
            ],
        ];

        $config = new AggregateConfig($write_databases);

        $newConfig = $config->setReadDatabase([
            'driver' => 'prometheus',
            'url' => 'http://prometheus.example.com:9090',
        ]);

        // Check that the original config is unchanged
        $readDatabase = $config->getReadDatabase();
        $this->assertNotNull($readDatabase);
        $this->assertEquals('influxdb', $readDatabase['driver']);
        $this->assertEquals('http://influxdb1.example.com:8086', $readDatabase['url']);

        // Check that the new config has the new read database
        $newReadDatabase = $newConfig->getReadDatabase();
        $this->assertNotNull($newReadDatabase);
        $this->assertEquals('prometheus', $newReadDatabase['driver']);
        $this->assertEquals('http://prometheus.example.com:9090', $newReadDatabase['url']);
    }

    public function test_set_read_database_missing_driver(): void
    {
        $write_databases = [
            [
                'driver' => 'influxdb',
                'url' => 'http://influxdb1.example.com:8086',
            ],
        ];

        $config = new AggregateConfig($write_databases);

        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('Driver not specified for read database');

        $config->setReadDatabase([
            // Missing driver
            'url' => 'http://prometheus.example.com:9090',
        ]);
    }
}

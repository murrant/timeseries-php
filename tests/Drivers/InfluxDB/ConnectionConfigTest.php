<?php

namespace TimeSeriesPhp\Tests\Drivers\InfluxDB;

use TimeSeriesPhp\Config\ConfigInterface;
use TimeSeriesPhp\Drivers\InfluxDB\ConnectionConfig;
use TimeSeriesPhp\Tests\Config\ConfigTestCase;

class ConnectionConfigTest extends ConfigTestCase
{
    protected function createConfig(array $config): ConfigInterface
    {
        return new ConnectionConfig($config);
    }
}

<?php

namespace TimeSeriesPhp\Tests\Config;

use TimeSeriesPhp\Config\ConfigInterface;
use TimeSeriesPhp\Drivers\InfluxDB\ConnectionConfig;

class ConnectionConfigTest extends ConfigTestCase
{
    protected function createConfig(array $config): ConfigInterface
    {
        return new ConnectionConfig($config);
    }
}

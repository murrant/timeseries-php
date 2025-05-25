<?php

namespace TimeSeriesPhp\Tests\Config;

use TimeSeriesPhp\Config\ConfigInterface;
use TimeSeriesPhp\Config\ConnectionConfig;

class ConnectionConfigTest extends ConfigTestCase
{
    protected function createConfig(array $config): ConfigInterface
    {
        return new ConnectionConfig($config);
    }
}

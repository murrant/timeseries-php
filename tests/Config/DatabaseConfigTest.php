<?php

namespace TimeSeriesPhp\Tests\Config;

use TimeSeriesPhp\Config\ConfigInterface;
use TimeSeriesPhp\Config\DatabaseConfig;

class DatabaseConfigTest extends ConfigTestCase
{
    protected function createConfig(array $config): ConfigInterface
    {
        return new DatabaseConfig($config);
    }
}

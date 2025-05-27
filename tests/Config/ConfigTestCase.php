<?php

namespace TimeSeriesPhp\Tests\Config;

use PHPUnit\Framework\TestCase;
use TimeSeriesPhp\Config\ConfigInterface;

abstract class ConfigTestCase extends TestCase
{
    abstract protected function createConfig(array $config): ConfigInterface;

    public function test_get_existing_key()
    {
        $config = $this->createConfig([
            'host' => 'localhost',
            'port' => 8086,
            'username' => 'admin',
            'password' => 'password',
            'database' => 'metrics',
            'ssl' => true,
            'timeout' => 30,
        ]);

        $this->assertEquals('localhost', $config->get('host'));
        $this->assertEquals(8086, $config->get('port'));
        $this->assertEquals('admin', $config->get('username'));
        $this->assertEquals('password', $config->get('password'));
        $this->assertEquals('metrics', $config->get('database'));
        $this->assertTrue($config->get('ssl'));
        $this->assertEquals(30, $config->get('timeout'));
    }

    public function test_get_non_existing_key_with_default()
    {
        $config = $this->createConfig(['host' => 'localhost']);

        $this->assertEquals('default', $config->get('non_existing', 'default'));
    }

    public function test_get_non_existing_key_without_default()
    {
        $config = $this->createConfig(['host' => 'localhost']);

        $this->assertNull($config->get('non_existing'));
    }
}

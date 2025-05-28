<?php

namespace TimeSeriesPhp\Tests\Config;

use PHPUnit\Framework\TestCase;
use TimeSeriesPhp\Config\ConfigInterface;

abstract class ConfigTestCase extends TestCase
{
    /**
     * @param  array<string, mixed>  $config
     */
    abstract protected function createConfig(array $config): ConfigInterface;

    public function test_get_existing_key(): void
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

        $this->assertEquals('localhost', $config->getString('host'));
        $this->assertEquals(8086, $config->getInt('port'));
        $this->assertEquals('admin', $config->getString('username'));
        $this->assertEquals('password', $config->getString('password'));
        $this->assertEquals('metrics', $config->getString('database'));
        $this->assertTrue($config->getBool('ssl'));
        $this->assertEquals(30, $config->getInt('timeout'));
    }

    public function test_get_non_existing_key_with_default(): void
    {
        $config = $this->createConfig(['host' => 'localhost']);

        $this->assertEquals('default', $config->get('non_existing', 'default'));
    }

    public function test_get_non_existing_key_without_default(): void
    {
        $config = $this->createConfig(['host' => 'localhost']);

        $this->assertNull($config->get('non_existing'));
    }
}

<?php

namespace TimeSeriesPhp\Tests\Config;

use TimeSeriesPhp\Config\AbstractConfig;
use TimeSeriesPhp\Config\ConfigInterface;

class AbstractConfigTest extends ConfigTestCase
{
    protected function createConfig(array $config): ConfigInterface
    {
        return new ConcreteConfig($config);
    }

    public function test_get_with_dot_notation(): void
    {
        $config = $this->createConfig([
            'database' => [
                'host' => 'localhost',
                'port' => 3306,
                'credentials' => [
                    'username' => 'root',
                    'password' => 'secret',
                ],
            ],
        ]);

        $this->assertEquals('localhost', $config->get('database.host'));
        $this->assertEquals(3306, $config->get('database.port'));
        $this->assertEquals('root', $config->get('database.credentials.username'));
        $this->assertEquals('secret', $config->get('database.credentials.password'));
        $this->assertNull($config->get('database.credentials.api_key'));
        $this->assertEquals('default', $config->get('database.credentials.api_key', 'default'));
    }

    public function test_set_with_dot_notation(): void
    {
        $config = $this->createConfig([
            'database' => [
                'host' => 'localhost',
            ],
        ]);

        $config->set('database.port', 3306);
        $this->assertEquals(3306, $config->get('database.port'));

        $config->set('database.credentials.username', 'root');
        $this->assertEquals('root', $config->get('database.credentials.username'));

        $config->set('new.nested.key', 'value');
        $this->assertEquals('value', $config->get('new.nested.key'));
    }

    public function test_has_with_dot_notation(): void
    {
        $config = $this->createConfig([
            'database' => [
                'host' => 'localhost',
                'credentials' => [
                    'username' => 'root',
                ],
            ],
        ]);

        $this->assertTrue($config->has('database.host'));
        $this->assertTrue($config->has('database.credentials.username'));
        $this->assertFalse($config->has('database.port'));
        $this->assertFalse($config->has('database.credentials.password'));
        $this->assertFalse($config->has('non.existent.key'));
    }

    public function test_get_typed_values_with_dot_notation(): void
    {
        $config = $this->createConfig([
            'database' => [
                'host' => 'localhost',
                'port' => 3306,
                'enabled' => true,
                'timeout' => 30.5,
                'options' => ['cache' => true],
            ],
        ]);

        $this->assertEquals('localhost', $config->getString('database.host'));
        $this->assertEquals(3306, $config->getInt('database.port'));
        $this->assertTrue($config->getBool('database.enabled'));
        $this->assertEquals(30.5, $config->getFloat('database.timeout'));
        $this->assertEquals(['cache' => true], $config->getArray('database.options'));
    }
}

/**
 * Concrete implementation of AbstractConfig for testing
 */
class ConcreteConfig extends AbstractConfig
{
    // No additional implementation needed
}

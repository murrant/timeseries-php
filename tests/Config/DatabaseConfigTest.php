<?php

namespace TimeSeriesPhp\Tests\Config;

use TimeSeriesPhp\Config\ConfigInterface;
use TimeSeriesPhp\Drivers\InfluxDB\DatabaseConfig;
use TimeSeriesPhp\Exceptions\ConfigurationException;

class DatabaseConfigTest extends ConfigTestCase
{
    protected function createConfig(array $config): ConfigInterface
    {
        return new DatabaseConfig($config);
    }

    public function test_get_connection_string()
    {
        // Test with host only
        $config = $this->createConfig(['host' => 'localhost']);
        $this->assertEquals('localhost/default', $config->getConnectionString());

        // Test with host and port
        $config = $this->createConfig(['host' => 'localhost', 'port' => 8086]);
        $this->assertEquals('localhost:8086/default', $config->getConnectionString());

        // Test with host, port, and database
        $config = $this->createConfig(['host' => 'localhost', 'port' => 8086, 'database' => 'metrics']);
        $this->assertEquals('localhost:8086/metrics', $config->getConnectionString());

        // Test with host and database, no port
        $config = $this->createConfig(['host' => 'localhost', 'database' => 'metrics']);
        $this->assertEquals('localhost/metrics', $config->getConnectionString());
    }

    public function test_has_auth()
    {
        // Test with no auth
        $config = $this->createConfig(['host' => 'localhost']);
        $this->assertFalse($config->hasAuth());

        // Test with auth
        $config = $this->createConfig(['host' => 'localhost', 'username' => 'admin']);
        $this->assertTrue($config->hasAuth());
    }

    public function test_get_auth_credentials()
    {
        // Test with no auth
        $config = $this->createConfig(['host' => 'localhost']);
        $credentials = $config->getAuthCredentials();
        $this->assertNull($credentials['username']);
        $this->assertNull($credentials['password']);

        // Test with username only
        $config = $this->createConfig(['host' => 'localhost', 'username' => 'admin']);
        $credentials = $config->getAuthCredentials();
        $this->assertEquals('admin', $credentials['username']);
        $this->assertNull($credentials['password']);

        // Test with username and password
        $config = $this->createConfig(['host' => 'localhost', 'username' => 'admin', 'password' => 'secret']);
        $credentials = $config->getAuthCredentials();
        $this->assertEquals('admin', $credentials['username']);
        $this->assertEquals('secret', $credentials['password']);
    }

    public function test_port_validator()
    {
        // Test with valid port
        $config = $this->createConfig(['host' => 'localhost', 'port' => 8086]);
        $this->assertEquals(8086, $config->get('port'));

        // Test with null port (should be valid)
        $config = $this->createConfig(['host' => 'localhost', 'port' => null]);
        $this->assertNull($config->get('port'));

        // Test with invalid port (too low)
        $this->expectException(ConfigurationException::class);
        $this->createConfig(['host' => 'localhost', 'port' => 0]);
    }

    public function test_port_validator_too_high()
    {
        // Test with invalid port (too high)
        $this->expectException(ConfigurationException::class);
        $this->createConfig(['host' => 'localhost', 'port' => 65536]);
    }

    public function test_timeout_validator()
    {
        // Test with valid timeout
        $config = $this->createConfig(['host' => 'localhost', 'timeout' => 30]);
        $this->assertEquals(30, $config->get('timeout'));

        // Test with invalid timeout (zero)
        $this->expectException(ConfigurationException::class);
        $this->createConfig(['host' => 'localhost', 'timeout' => 0]);
    }

    public function test_retry_attempts_validator()
    {
        // Test with valid retry_attempts
        $config = $this->createConfig(['host' => 'localhost', 'retry_attempts' => 3]);
        $this->assertEquals(3, $config->get('retry_attempts'));

        // Test with zero retry_attempts (should be valid)
        $config = $this->createConfig(['host' => 'localhost', 'retry_attempts' => 0]);
        $this->assertEquals(0, $config->get('retry_attempts'));

        // Test with invalid retry_attempts (negative)
        $this->expectException(ConfigurationException::class);
        $this->createConfig(['host' => 'localhost', 'retry_attempts' => -1]);
    }

    public function test_retry_delay_validator()
    {
        // Test with valid retry_delay
        $config = $this->createConfig(['host' => 'localhost', 'retry_delay' => 1000]);
        $this->assertEquals(1000, $config->get('retry_delay'));

        // Test with zero retry_delay (should be valid)
        $config = $this->createConfig(['host' => 'localhost', 'retry_delay' => 0]);
        $this->assertEquals(0, $config->get('retry_delay'));

        // Test with invalid retry_delay (negative)
        $this->expectException(ConfigurationException::class);
        $this->createConfig(['host' => 'localhost', 'retry_delay' => -1]);
    }
}

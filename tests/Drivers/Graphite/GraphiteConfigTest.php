<?php

namespace TimeSeriesPhp\Tests\Drivers\Graphite;

use PHPUnit\Framework\TestCase;
use TimeSeriesPhp\Drivers\Graphite\GraphiteConfig;
use TimeSeriesPhp\Exceptions\ConfigurationException;

class GraphiteConfigTest extends TestCase
{
    public function test_default_values()
    {
        $config = new GraphiteConfig;

        $this->assertEquals('localhost', $config->get('host'));
        $this->assertEquals(2003, $config->get('port'));
        $this->assertEquals('tcp', $config->get('protocol'));
        $this->assertEquals(30, $config->get('timeout'));
        $this->assertEquals('', $config->get('prefix'));
        $this->assertEquals(500, $config->get('batch_size'));
        $this->assertEquals('localhost', $config->get('web_host'));
        $this->assertEquals(8080, $config->get('web_port'));
        $this->assertEquals('http', $config->get('web_protocol'));
        $this->assertEquals('/render', $config->get('web_path'));
    }

    public function test_custom_values()
    {
        $config = new GraphiteConfig([
            'host' => 'graphite.example.com',
            'port' => 2004,
            'protocol' => 'udp',
            'timeout' => 60,
            'prefix' => 'myapp',
            'batch_size' => 1000,
            'web_host' => 'graphite-web.example.com',
            'web_port' => 8081,
            'web_protocol' => 'https',
            'web_path' => '/api/render',
        ]);

        $this->assertEquals('graphite.example.com', $config->get('host'));
        $this->assertEquals(2004, $config->get('port'));
        $this->assertEquals('udp', $config->get('protocol'));
        $this->assertEquals(60, $config->get('timeout'));
        $this->assertEquals('myapp', $config->get('prefix'));
        $this->assertEquals(1000, $config->get('batch_size'));
        $this->assertEquals('graphite-web.example.com', $config->get('web_host'));
        $this->assertEquals(8081, $config->get('web_port'));
        $this->assertEquals('https', $config->get('web_protocol'));
        $this->assertEquals('/api/render', $config->get('web_path'));
    }

    public function test_missing_required_fields()
    {
        $this->expectException(ConfigurationException::class);

        // Create config with empty host (required field)
        new GraphiteConfig(['host' => '']);
    }

    public function test_invalid_protocol()
    {
        $this->expectException(ConfigurationException::class);

        // Create config with invalid protocol
        new GraphiteConfig([
            'host' => 'graphite.example.com',
            'port' => 2003,
            'protocol' => 'invalid',
        ]);
    }

    public function test_invalid_web_protocol()
    {
        $this->expectException(ConfigurationException::class);

        // Create config with invalid web protocol
        new GraphiteConfig([
            'host' => 'graphite.example.com',
            'port' => 2003,
            'web_protocol' => 'invalid',
        ]);
    }

    public function test_get_connection_string()
    {
        $config = new GraphiteConfig([
            'host' => 'graphite.example.com',
            'port' => 2004,
        ]);

        $this->assertEquals('graphite.example.com:2004', $config->getConnectionString());
    }

    public function test_get_web_url()
    {
        $config = new GraphiteConfig([
            'web_host' => 'graphite-web.example.com',
            'web_port' => 8081,
            'web_protocol' => 'https',
            'web_path' => '/api/render',
        ]);

        $this->assertEquals('https://graphite-web.example.com:8081/api/render', $config->getWebUrl());
    }

    public function test_driver_name()
    {
        $config = new GraphiteConfig;

        $this->assertEquals('graphite', $config->getDriverName());
    }
}

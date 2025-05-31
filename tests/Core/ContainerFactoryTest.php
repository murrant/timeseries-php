<?php

declare(strict_types=1);

namespace TimeSeriesPhp\Tests\Core;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use TimeSeriesPhp\Core\ContainerFactory;
use TimeSeriesPhp\Exceptions\TSDBException;
use TimeSeriesPhp\Services\ConfigurationManager;

class ContainerFactoryTest extends TestCase
{
    private string $configDir;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a temporary config directory for testing
        $this->configDir = sys_get_temp_dir() . '/timeseries-php-test-' . uniqid();
        mkdir($this->configDir);
        mkdir($this->configDir . '/packages');

        // Create a test services.yaml file
        $servicesContent = <<<YAML
services:
    _defaults:
        autowire: true
        autoconfigure: true
        public: true

    TimeSeriesPhp\Services\ConfigurationManager:
        arguments: ['%kernel.project_dir%/config']
        public: true
YAML;

        file_put_contents($this->configDir . '/services.yaml', $servicesContent);

        // Create a test configuration file
        $configContent = <<<YAML
default_driver: 'test_driver'
drivers:
    test_driver:
        url: 'http://localhost:9999'
YAML;

        file_put_contents($this->configDir . '/packages/config.yaml', $configContent);
    }

    protected function tearDown(): void
    {
        // Clean up the temporary config directory
        if (file_exists($this->configDir . '/packages/config.yaml')) {
            unlink($this->configDir . '/packages/config.yaml');
        }
        if (file_exists($this->configDir . '/services.yaml')) {
            unlink($this->configDir . '/services.yaml');
        }
        if (is_dir($this->configDir . '/packages')) {
            rmdir($this->configDir . '/packages');
        }
        if (is_dir($this->configDir)) {
            rmdir($this->configDir);
        }

        parent::tearDown();
    }

    public function testCreate(): void
    {
        $container = ContainerFactory::create($this->configDir);

        $this->assertInstanceOf(ContainerBuilder::class, $container);
        $this->assertTrue($container->isCompiled());

        // Test that the container has the expected services
        $this->assertTrue($container->has(ConfigurationManager::class));
    }

    public function testCreateWithInvalidConfigDir(): void
    {
        $this->expectException(TSDBException::class);
        ContainerFactory::create('/non/existent/directory');
    }
}

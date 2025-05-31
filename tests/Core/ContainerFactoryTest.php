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
        $this->configDir = sys_get_temp_dir().'/timeseries-php-test-'.uniqid();
        mkdir($this->configDir);
        mkdir($this->configDir.'/packages');

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

    Psr\Log\LoggerInterface:
        class: TimeSeriesPhp\Services\Logger
        arguments:
            \$config: '%logging%'
        public: true

    # Cache drivers are registered via the CacheDriverCompilerPass
    TimeSeriesPhp\Services\Cache\ArrayCacheDriver:
        public: true
YAML;

        file_put_contents($this->configDir.'/services.yaml', $servicesContent);

        // Create a test configuration file
        $configContent = <<<'YAML'
parameters:
    default_driver: 'test_driver'
    drivers:
        test_driver:
            url: 'http://localhost:9999'
    logging:
        level: 'info'
        file: null
        max_size: 10485760
        max_files: 5
        timestamps: true
        format: 'simple'
    cache:
        enabled: true
        ttl: 3600
        driver: 'array'
        prefix: 'test_'
YAML;

        file_put_contents($this->configDir.'/packages/config.yaml', $configContent);
    }

    protected function tearDown(): void
    {
        // Clean up the temporary config directory
        if (file_exists($this->configDir.'/packages/config.yaml')) {
            unlink($this->configDir.'/packages/config.yaml');
        }
        if (file_exists($this->configDir.'/services.yaml')) {
            unlink($this->configDir.'/services.yaml');
        }
        if (is_dir($this->configDir.'/packages')) {
            rmdir($this->configDir.'/packages');
        }
        if (is_dir($this->configDir)) {
            rmdir($this->configDir);
        }

        parent::tearDown();
    }

    public function test_create(): void
    {
        $container = ContainerFactory::create($this->configDir);

        $this->assertInstanceOf(ContainerBuilder::class, $container);
        $this->assertTrue($container->isCompiled());

        // Test that the container has the expected services
        $this->assertTrue($container->has(ConfigurationManager::class));
        $this->assertTrue($container->has('Psr\Log\LoggerInterface'));
        $this->assertTrue($container->has('TimeSeriesPhp\Services\Cache\ArrayCacheDriver'));
    }

    public function test_create_with_invalid_config_dir(): void
    {
        $this->expectException(TSDBException::class);
        ContainerFactory::create('/non/existent/directory');
    }
}

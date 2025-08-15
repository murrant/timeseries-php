<?php

declare(strict_types=1);

namespace TimeSeriesPhp\Tests\Laravel;

use Illuminate\Container\Container;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use TimeSeriesPhp\Contracts\Driver\TimeSeriesInterface;
use TimeSeriesPhp\Core\Driver\DriverFactory;

abstract class LaravelTestCase extends TestCase
{
    use MockeryPHPUnitIntegration;

    protected Container $container;

    protected LoggerInterface $logger;

    protected DriverFactory $driverFactory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->container = new Container;
        $this->logger = Mockery::mock(LoggerInterface::class);
        $this->driverFactory = Mockery::mock(DriverFactory::class);

        // Set up the container with our mocks
        $this->container->instance(LoggerInterface::class, $this->logger);
        $this->container->instance(DriverFactory::class, $this->driverFactory);
        $this->container->instance(ContainerInterface::class, $this->container);

        // Set up the config with a repository
        $this->container->instance('config', new \Illuminate\Config\Repository([
            'timeseries' => [
                'default' => 'influxdb',
                'drivers' => [
                    'influxdb' => [
                        'class' => \TimeSeriesPhp\Drivers\InfluxDB\InfluxDBDriver::class,
                        'url' => 'http://localhost:8086',
                        'token' => 'test-token',
                        'org' => 'test-org',
                        'bucket' => 'test-bucket',
                    ],
                    'null' => [
                        'class' => \TimeSeriesPhp\Drivers\Null\NullDriver::class,
                    ],
                ],
                'logging' => [
                    'enabled' => true,
                    'level' => 'debug',
                ],
            ],
        ]));
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    protected function mockDriver(string $driverClass): TimeSeriesInterface
    {
        $driver = Mockery::mock($driverClass);
        $this->driverFactory->shouldReceive('create')
            ->andReturn($driver);

        return $driver;
    }
}

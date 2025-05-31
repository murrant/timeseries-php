<?php

declare(strict_types=1);

namespace TimeSeriesPhp\Tests\Core\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use TimeSeriesPhp\Core\DependencyInjection\DriverCompilerPass;
use TimeSeriesPhp\Drivers\Null\NullConfig;
use TimeSeriesPhp\Drivers\Null\NullDriver;


class DriverCompilerPassTest extends TestCase
{
    private ContainerBuilder $container;

    protected function setUp(): void
    {
        $this->container = new ContainerBuilder;

        // Register the null driver
        $this->container->register(NullDriver::class, NullDriver::class)
            ->setAutoconfigured(true)
            ->setAutowired(true);

        // Register the null driver configuration
        $this->container->register(NullConfig::class, NullConfig::class)
            ->setAutoconfigured(true)
            ->setAutowired(true);
    }

    public function test_process_tags_drivers(): void
    {
        // Add the driver compiler pass
        $this->container->addCompilerPass(new DriverCompilerPass);

        // Compile the container
        $this->container->compile();

        // Get the drivers parameter
        $drivers = $this->container->getParameter('timeseries.drivers');

        // Assert that the drivers parameter is an array
        $this->assertIsArray($drivers);

        // Assert that the null driver is registered
        $this->assertArrayHasKey('null', $drivers);

        // Assert that the null driver service ID is correct
        $this->assertEquals(NullDriver::class, $drivers['null']);

        // Get the null driver definition
        $definition = $this->container->getDefinition(NullDriver::class);

        // Assert that the definition has the driver tag
        $this->assertTrue($definition->hasTag('timeseries.driver'));

        // Get the driver tag
        $tags = $definition->getTag('timeseries.driver');

        // Assert that the tag has the correct name
        $this->assertEquals('null', $tags[0]['name']);
    }

    public function test_process_registers_config_class(): void
    {
        // Add the driver compiler pass
        $this->container->addCompilerPass(new DriverCompilerPass);

        // Compile the container
        $this->container->compile();

        // Assert that the container has the config class
        $this->assertTrue($this->container->has(NullConfig::class));

        // Get the config class definition
        $definition = $this->container->getDefinition(NullConfig::class);

        // Assert that the definition is autoconfigured
        $this->assertTrue($definition->isAutoconfigured());

        // Assert that the definition is autowired
        $this->assertTrue($definition->isAutowired());
    }
}

<?php

declare(strict_types=1);

namespace TimeSeriesPhp\Tests\Core\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use TimeSeriesPhp\Core\Attributes\Driver;
use TimeSeriesPhp\Core\DependencyInjection\DriverCompilerPass;
use TimeSeriesPhp\Drivers\Example\ExampleDriver;
use TimeSeriesPhp\Drivers\Example\ExampleDriverConfiguration;

class DriverCompilerPassTest extends TestCase
{
    private ContainerBuilder $container;

    protected function setUp(): void
    {
        $this->container = new ContainerBuilder();
        
        // Register the example driver
        $this->container->register(ExampleDriver::class, ExampleDriver::class)
            ->setAutoconfigured(true)
            ->setAutowired(true);
            
        // Register the example driver configuration
        $this->container->register(ExampleDriverConfiguration::class, ExampleDriverConfiguration::class)
            ->setAutoconfigured(true)
            ->setAutowired(true);
    }
    
    public function testProcessTagsDrivers(): void
    {
        // Add the driver compiler pass
        $this->container->addCompilerPass(new DriverCompilerPass());
        
        // Compile the container
        $this->container->compile();
        
        // Get the drivers parameter
        $drivers = $this->container->getParameter('timeseries.drivers');
        
        // Assert that the drivers parameter is an array
        $this->assertIsArray($drivers);
        
        // Assert that the example driver is registered
        $this->assertArrayHasKey('example', $drivers);
        
        // Assert that the example driver service ID is correct
        $this->assertEquals(ExampleDriver::class, $drivers['example']);
        
        // Get the example driver definition
        $definition = $this->container->getDefinition(ExampleDriver::class);
        
        // Assert that the definition has the driver tag
        $this->assertTrue($definition->hasTag('timeseries.driver'));
        
        // Get the driver tag
        $tags = $definition->getTag('timeseries.driver');
        
        // Assert that the tag has the correct name
        $this->assertEquals('example', $tags[0]['name']);
    }
    
    public function testProcessRegistersConfigClass(): void
    {
        // Add the driver compiler pass
        $this->container->addCompilerPass(new DriverCompilerPass());
        
        // Compile the container
        $this->container->compile();
        
        // Assert that the container has the config class
        $this->assertTrue($this->container->has(ExampleDriverConfiguration::class));
        
        // Get the config class definition
        $definition = $this->container->getDefinition(ExampleDriverConfiguration::class);
        
        // Assert that the definition is autoconfigured
        $this->assertTrue($definition->isAutoconfigured());
        
        // Assert that the definition is autowired
        $this->assertTrue($definition->isAutowired());
    }
}

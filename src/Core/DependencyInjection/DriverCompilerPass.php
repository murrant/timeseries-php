<?php

declare(strict_types=1);

namespace TimeSeriesPhp\Core\DependencyInjection;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use TimeSeriesPhp\Core\Attributes\Driver;

/**
 * Compiler pass to register drivers in the container
 */
class DriverCompilerPass implements CompilerPassInterface
{
    /**
     * Process the container to find and register drivers
     *
     * @param  ContainerBuilder  $container  The container builder
     */
    public function process(ContainerBuilder $container): void
    {
        // Find all services with the Driver attribute
        $drivers = [];

        foreach ($container->getDefinitions() as $id => $definition) {
            // Skip if the definition doesn't have a class
            if (! $definition->getClass()) {
                continue;
            }

            // Get the class name
            $className = $definition->getClass();

            // Skip if the class doesn't exist
            if (! class_exists($className)) {
                continue;
            }

            // Get the reflection class
            $reflectionClass = new \ReflectionClass($className);

            // Check if the class has the Driver attribute
            $attribute = $reflectionClass->getAttributes(Driver::class)[0] ?? null;

            if ($attribute) {
                // Get the attribute instance
                $driver = $attribute->newInstance();

                // Add the driver to the list
                $drivers[$driver->name] = $id;

                // Tag the service as a driver and make it public
                $definition->addTag('timeseries.driver', ['name' => $driver->name])
                    ->setPublic(true);

                // Also register a PSR-11 friendly alias so factories can resolve drivers without
                // accessing parameters. Example: timeseries.driver.influxdb -> <service id>
                $driverAliasId = sprintf('%s.%s.%s', 'timeseries', 'driver', $driver->name);
                $container->setAlias($driverAliasId, $id)->setPublic(true);

                // If the driver has a config class, register it as a service
                if ($driver->configClass && class_exists($driver->configClass)) {
                    // Register the config class as a service if it's not already registered
                    if (! $container->has($driver->configClass)) {
                        $container->register($driver->configClass, $driver->configClass)
                            ->setAutoconfigured(true)
                            ->setAutowired(true)
                            ->setPublic(true);
                    } else {
                        // Make sure the existing config class is public
                        $container->getDefinition($driver->configClass)
                            ->setPublic(true);
                    }

                    // Set the config as a constructor argument for the driver if the driver has a $config parameter
                    $driverReflection = new \ReflectionClass($className);
                    $constructor = $driverReflection->getConstructor();

                    if ($constructor) {
                        $hasConfigParam = false;
                        foreach ($constructor->getParameters() as $parameter) {
                            if ($parameter->getName() === 'config') {
                                $hasConfigParam = true;
                                break;
                            }
                        }

                        if ($hasConfigParam) {
                            $definition->setArgument('$config', $container->getDefinition($driver->configClass));
                        }
                    }
                }

                // If the driver has a query builder class, register it as a service
                if ($driver->queryBuilderClass && class_exists($driver->queryBuilderClass)) {
                    // Register the query builder class as a service if it's not already registered
                    if (! $container->has($driver->queryBuilderClass)) {
                        $container->register($driver->queryBuilderClass, $driver->queryBuilderClass)
                            ->setAutoconfigured(true)
                            ->setAutowired(true)
                            ->setPublic(true);
                    } else {
                        // Make sure the existing query builder class is public
                        $container->getDefinition($driver->queryBuilderClass)
                            ->setPublic(true);
                    }

                    // Create an alias from QueryBuilderInterface to the query builder class for this driver
                    // This allows Symfony to inject the query builder into the driver's constructor
                    $aliasId = sprintf('%s.%s.query_builder', 'timeseries', $driver->name);
                    $container->setAlias($aliasId, $driver->queryBuilderClass)
                        ->setPublic(true);

                    // Set the query builder as a constructor argument for the driver
                    $definition->setArgument('$queryBuilder', $container->getDefinition($driver->queryBuilderClass));
                }

                // If the driver has a schema manager class, register it as a service
                if ($driver->schemaManagerClass && class_exists($driver->schemaManagerClass)) {
                    // Register the schema manager class as a service if it's not already registered
                    if (! $container->has($driver->schemaManagerClass)) {
                        $container->register($driver->schemaManagerClass, $driver->schemaManagerClass)
                            ->setAutoconfigured(true)
                            ->setAutowired(true)
                            ->setPublic(true);
                    } else {
                        // Make sure the existing schema manager class is public
                        $container->getDefinition($driver->schemaManagerClass)
                            ->setPublic(true);
                    }

                    // Create an alias from SchemaManagerInterface to the schema manager class for this driver
                    $aliasId = sprintf('%s.%s.schema_manager', 'timeseries', $driver->name);
                    $container->setAlias($aliasId, $driver->schemaManagerClass)
                        ->setPublic(true);
                }
            }
        }

        // Register the drivers as a parameter
        $container->setParameter('timeseries.drivers', $drivers);
    }
}

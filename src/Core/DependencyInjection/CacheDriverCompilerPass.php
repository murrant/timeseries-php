<?php

declare(strict_types=1);

namespace TimeSeriesPhp\Core\DependencyInjection;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use TimeSeriesPhp\Core\Attributes\CacheDriver;

/**
 * Compiler pass to register cache drivers in the container
 */
class CacheDriverCompilerPass implements CompilerPassInterface
{
    /**
     * Process the container to find and register cache drivers
     *
     * @param  ContainerBuilder  $container  The container builder
     */
    public function process(ContainerBuilder $container): void
    {
        // Find all services with the CacheDriver attribute
        $cacheDrivers = [];

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

            // Check if the class has the CacheDriver attribute
            $attribute = $reflectionClass->getAttributes(CacheDriver::class)[0] ?? null;

            if ($attribute) {
                // Get the attribute instance
                $cacheDriver = $attribute->newInstance();

                // Add the cache driver to the list
                $cacheDrivers[$cacheDriver->name] = $id;

                // Tag the service as a cache driver and make it public
                $definition->addTag('timeseries.cache_driver', ['name' => $cacheDriver->name])
                    ->setPublic(true);

                // If the cache driver has a config class, register it as a service
                if ($cacheDriver->configClass && class_exists($cacheDriver->configClass)) {
                    // Register the config class as a service if it's not already registered
                    if (! $container->has($cacheDriver->configClass)) {
                        $container->register($cacheDriver->configClass, $cacheDriver->configClass)
                            ->setAutoconfigured(true)
                            ->setAutowired(true)
                            ->setPublic(true);
                    } else {
                        // Make sure the existing config class is public
                        $container->getDefinition($cacheDriver->configClass)
                            ->setPublic(true);
                    }
                }
            }
        }

        // Register the cache drivers as a parameter
        $container->setParameter('timeseries.cache_drivers', $cacheDrivers);
    }
}

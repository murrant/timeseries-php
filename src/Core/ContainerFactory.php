<?php

declare(strict_types=1);

namespace TimeSeriesPhp\Core;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use TimeSeriesPhp\Core\DependencyInjection\DriverCompilerPass;
use TimeSeriesPhp\Exceptions\TSDBException;

/**
 * Factory for creating and configuring the Symfony DI container
 */
class ContainerFactory
{
    /**
     * Create a new container instance
     *
     * @param  string  $configDir  The directory containing configuration files
     * @return ContainerBuilder The configured container
     *
     * @throws TSDBException If the container cannot be built
     */
    public static function create(?string $configDir = null): ContainerBuilder
    {
        $configDir = $configDir ?? dirname(__DIR__, 2).'/config';

        try {
            $container = new ContainerBuilder;

            // Set the project directory parameter
            $projectDir = dirname($configDir);
            $container->setParameter('kernel.project_dir', $projectDir);
            $container->setParameter('kernel.cache_dir', $projectDir.'/var/cache');

            // Add compiler passes
            $container->addCompilerPass(new DriverCompilerPass());

            $loader = new YamlFileLoader($container, new FileLocator($configDir));
            $loader->load('services.yaml');

            // Load all configuration files from the packages directory
            $packagesDir = $configDir.'/packages';
            if (is_dir($packagesDir)) {
                $files = scandir($packagesDir);
                foreach ($files as $file) {
                    if (pathinfo($file, PATHINFO_EXTENSION) === 'yaml') {
                        $loader->load('packages/'.$file);
                    }
                }
            }

            $container->compile();

            return $container;
        } catch (\Exception $e) {
            throw new TSDBException('Failed to build container: '.$e->getMessage(), 0, $e);
        }
    }
}

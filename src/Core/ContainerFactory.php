<?php

declare(strict_types=1);

namespace TimeSeriesPhp\Core;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;
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
        $configDir ??= dirname(__DIR__, 2).'/config';

        try {
            $container = new ContainerBuilder;

            // Enable environment variable processing
            $container->getParameterBag()->add([
                'env(TSDB_DEFAULT_DRIVER)' => 'influxdb',
                'env(INFLUXDB_URL)' => 'http://localhost:8086',
                'env(INFLUXDB_TOKEN)' => '',
                'env(INFLUXDB_ORG)' => '',
                'env(INFLUXDB_BUCKET)' => 'default',
                'env(INFLUXDB_PRECISION)' => 'ns',
                'env(PROMETHEUS_URL)' => 'http://localhost:9090',
            ]);

            // Set the project directory parameter
            $projectDir = dirname($configDir);
            $container->setParameter('kernel.project_dir', $projectDir);
            $container->setParameter('kernel.cache_dir', $projectDir.'/var/cache');

            // Set required parameters for FrameworkBundle
            $container->setParameter('kernel.secret', 'timeseries-php-secret');
            $container->setParameter('kernel.debug', true);
            $container->setParameter('kernel.environment', 'dev');
            $container->setParameter('kernel.container_class', 'TimeSeriesPhpContainer');

            // Add compiler passes
            $container->addCompilerPass(new DriverCompilerPass);

            // Load bundles from bundles.php if it exists
            $bundlesFile = $configDir.'/bundles.php';
            if (file_exists($bundlesFile)) {
                $bundles = require $bundlesFile;
                foreach ($bundles as $bundleClass => $environments) {
                    if (isset($environments['all']) && $environments['all'] === true || 
                        isset($environments[$container->getParameter('kernel.environment')]) && 
                        $environments[$container->getParameter('kernel.environment')] === true) {
                        if (class_exists($bundleClass)) {
                            $bundle = new $bundleClass();
                            if ($bundle instanceof BundleInterface) {
                                $bundle->build($container);
                            }
                        }
                    }
                }
            }

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

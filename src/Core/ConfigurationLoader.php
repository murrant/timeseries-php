<?php

declare(strict_types=1);

namespace TimeSeriesPhp\Core;

use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\Yaml\Yaml;
use TimeSeriesPhp\Exceptions\TSDBException;

/**
 * Loads and processes configuration from YAML files
 */
class ConfigurationLoader
{
    /**
     * Load configuration from a YAML file
     *
     * @param  string  $configFile  The path to the configuration file
     * @param  string  $configDir  The directory containing configuration files
     * @return array The loaded configuration
     *
     * @throws TSDBException If the configuration file cannot be loaded
     */
    public static function loadFromFile(string $configFile, ?string $configDir = null): array
    {
        $configDir = $configDir ?? dirname(__DIR__, 2).'/config';
        $fullPath = $configDir.'/'.$configFile;

        if (! file_exists($fullPath)) {
            throw new TSDBException("Configuration file not found: $fullPath");
        }

        try {
            return Yaml::parseFile($fullPath);
        } catch (\Exception $e) {
            throw new TSDBException('Failed to parse configuration file: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * Load configuration from all YAML files in a directory
     *
     * @param  string  $configDir  The directory containing configuration files
     * @return array The loaded configuration
     *
     * @throws TSDBException If the configuration files cannot be loaded
     */
    public static function loadFromDirectory(?string $configDir = null): array
    {
        $configDir = $configDir ?? dirname(__DIR__, 2).'/config';
        $packagesDir = $configDir.'/packages';

        if (! is_dir($packagesDir)) {
            throw new TSDBException("Configuration directory not found: $packagesDir");
        }

        $config = [];
        $files = scandir($packagesDir);

        foreach ($files as $file) {
            if (pathinfo($file, PATHINFO_EXTENSION) === 'yaml') {
                $fileConfig = self::loadFromFile('packages/'.$file, $configDir);
                $config = array_merge_recursive($config, $fileConfig);
            }
        }

        return $config;
    }

    /**
     * Process configuration with a configuration definition
     *
     * @param  array  $configs  The configuration array
     * @param  object  $definition  The configuration definition
     * @return array The processed configuration
     */
    public static function processConfiguration(array $configs, object $definition): array
    {
        $processor = new Processor;

        return $processor->processConfiguration($definition, $configs);
    }
}

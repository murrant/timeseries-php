<?php

declare(strict_types=1);

namespace TimeSeriesPhp\Core;

use Symfony\Component\Config\Definition\ConfigurationInterface;
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
     * @param  ?string  $configDir  The directory containing configuration files
     * @return array<string, mixed> The loaded configuration
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
            /** @var array<string, mixed> $config */
            $config = Yaml::parseFile($fullPath);

            return $config;
        } catch (\Exception $e) {
            throw new TSDBException('Failed to parse configuration file: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * Load configuration from all YAML files in a directory
     *
     * @param  ?string  $configDir  The directory containing configuration files
     * @return array<string, mixed> The loaded configuration
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

        /** @var array<string, mixed> $config */
        return $config;
    }

    /**
     * Process configuration with a configuration definition
     *
     * @param  array<int, array<string, mixed>>  $configs  The configuration array
     * @param  ConfigurationInterface  $definition  The configuration definition
     * @return array<string, mixed> The processed configuration
     */
    public static function processConfiguration(array $configs, ConfigurationInterface $definition): array
    {
        $processor = new Processor;

        /** @var array<string, mixed> $processConfiguration */
        $processConfiguration = $processor->processConfiguration($definition, $configs);

        return $processConfiguration;
    }
}

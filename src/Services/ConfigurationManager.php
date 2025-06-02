<?php

declare(strict_types=1);

namespace TimeSeriesPhp\Services;

use TimeSeriesPhp\Core\Configuration;
use TimeSeriesPhp\Core\ConfigurationLoader;
use TimeSeriesPhp\Exceptions\Config\ConfigurationException;
use TimeSeriesPhp\Exceptions\TSDBException;

/**
 * Service for managing and accessing configuration
 */
class ConfigurationManager
{
    /**
     * @var array<string, mixed> The processed configuration
     */
    private readonly array $config;

    /**
     * Create a new ConfigurationManager instance
     *
     * @param  string|null  $configDir  The directory containing configuration files
     *
     * @throws TSDBException If the configuration cannot be loaded
     */
    public function __construct(?string $configDir = null)
    {
        $configDir ??= dirname(__DIR__, 2).'/config';

        // Load configuration from all files in the packages directory
        $configs = [ConfigurationLoader::loadFromDirectory($configDir)];

        // Process the configuration with the configuration definition
        $this->config = ConfigurationLoader::processConfiguration($configs, new Configuration);
    }

    /**
     * Get a specific configuration value
     *
     * @param  string  $key  The configuration key (dot notation)
     * @param  mixed  $default  The default value to return if the key is not found
     * @return mixed The configuration value
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $keys = explode('.', $key);
        $value = $this->config;

        foreach ($keys as $k) {
            if (! is_array($value) || ! array_key_exists($k, $value)) {
                return $default;
            }

            $value = $value[$k];
        }

        return $value;
    }

    /**
     * Get the default driver configuration
     *
     * @return array<string, mixed> The default driver configuration
     *
     * @throws ConfigurationException If the default driver is not configured
     */
    public function getDefaultDriverConfig(): array
    {
        $defaultDriver = $this->get('default_driver');

        if (! is_string($defaultDriver)) {
            throw new ConfigurationException('Default driver must be a string');
        }

        $driverConfig = $this->get("drivers.$defaultDriver");

        if ($driverConfig === null) {
            throw new ConfigurationException("Default driver '$defaultDriver' is not configured");
        }

        if (! is_array($driverConfig)) {
            throw new ConfigurationException("Configuration for driver '$defaultDriver' must be an array");
        }

        /** @var array<string, mixed> $driverConfig */
        return $driverConfig;
    }

    /**
     * Get a specific driver configuration
     *
     * @param  string  $driver  The driver name
     * @return array<string, mixed> The driver configuration
     *
     * @throws ConfigurationException If the driver is not configured
     */
    public function getDriverConfig(string $driver): array
    {
        $driverConfig = $this->get("drivers.$driver");

        if ($driverConfig === null) {
            throw new ConfigurationException("Driver '$driver' is not configured");
        }

        if (! is_array($driverConfig)) {
            throw new ConfigurationException("Configuration for driver '$driver' must be an array");
        }

        /** @var array<string, mixed> $driverConfig */
        return $driverConfig;
    }
}

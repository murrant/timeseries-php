<?php

declare(strict_types=1);

namespace TimeSeriesPhp\Services;

use TimeSeriesPhp\Core\Configuration;
use TimeSeriesPhp\Core\ConfigurationLoader;
use TimeSeriesPhp\Exceptions\TSDBException;

/**
 * Service for managing and accessing configuration
 */
class ConfigurationManager
{
    /**
     * @var array The processed configuration
     */
    private array $config;
    
    /**
     * Create a new ConfigurationManager instance
     *
     * @param string|null $configDir The directory containing configuration files
     * @throws TSDBException If the configuration cannot be loaded
     */
    public function __construct(string $configDir = null)
    {
        $configDir = $configDir ?? dirname(__DIR__, 2) . '/config';
        
        // Load configuration from all files in the packages directory
        $configs = [ConfigurationLoader::loadFromDirectory($configDir)];
        
        // Process the configuration with the configuration definition
        $this->config = ConfigurationLoader::processConfiguration($configs, new Configuration());
    }
    
    /**
     * Get the entire configuration
     *
     * @return array The entire configuration
     */
    public function getConfig(): array
    {
        return $this->config;
    }
    
    /**
     * Get a specific configuration value
     *
     * @param string $key The configuration key (dot notation)
     * @param mixed $default The default value to return if the key is not found
     * @return mixed The configuration value
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $keys = explode('.', $key);
        $value = $this->config;
        
        foreach ($keys as $k) {
            if (!is_array($value) || !array_key_exists($k, $value)) {
                return $default;
            }
            
            $value = $value[$k];
        }
        
        return $value;
    }
    
    /**
     * Get the default driver configuration
     *
     * @return array The default driver configuration
     * @throws TSDBException If the default driver is not configured
     */
    public function getDefaultDriverConfig(): array
    {
        $defaultDriver = $this->get('default_driver');
        $driverConfig = $this->get("drivers.$defaultDriver");
        
        if ($driverConfig === null) {
            throw new TSDBException("Default driver '$defaultDriver' is not configured");
        }
        
        return $driverConfig;
    }
    
    /**
     * Get a specific driver configuration
     *
     * @param string $driver The driver name
     * @return array The driver configuration
     * @throws TSDBException If the driver is not configured
     */
    public function getDriverConfig(string $driver): array
    {
        $driverConfig = $this->get("drivers.$driver");
        
        if ($driverConfig === null) {
            throw new TSDBException("Driver '$driver' is not configured");
        }
        
        return $driverConfig;
    }
}

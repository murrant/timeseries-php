<?php

namespace TimeSeriesPhp\Config;

use TimeSeriesPhp\Exceptions\ConfigurationException;

/**
 * Factory for creating general configuration objects
 *
 * For driver-specific configurations, use TSDBFactory::createConfig()
 */
class ConfigFactory
{
    /**
     * Map of configuration types to their classes
     *
     * @var array<string, class-string<ConfigInterface>>
     */
    private static array $configTypes = [
        'cache' => CacheConfig::class,
        'logging' => LoggingConfig::class,
    ];

    /**
     * Create a configuration instance
     *
     * @param  array<string, mixed>  $config
     *
     * @throws ConfigurationException
     */
    public static function create(string $type, array $config = []): ConfigInterface
    {
        if (! isset(self::$configTypes[$type])) {
            throw new ConfigurationException("Unknown configuration type: {$type}");
        }

        $className = self::$configTypes[$type];

        return new $className($config);
    }

    /**
     * Register a configuration type
     *
     * @throws ConfigurationException
     */
    public static function registerConfigType(string $type, string $className): void
    {
        if (! is_subclass_of($className, ConfigInterface::class)) {
            throw new ConfigurationException('Config class must implement ConfigInterface');
        }

        self::$configTypes[$type] = $className;
    }

    /**
     * Get available configuration types
     *
     * @return array<int, string>
     */
    public static function getAvailableTypes(): array
    {
        return array_keys(self::$configTypes);
    }

    /**
     * Create multiple configuration instances from an array
     *
     * @param  array<string, array<string, mixed>>  $configs
     * @return array<string, ConfigInterface>
     *
     * @throws ConfigurationException
     */
    public static function createFromArray(array $configs): array
    {
        $instances = [];

        foreach ($configs as $type => $config) {
            $instances[$type] = self::create($type, $config);
        }

        return $instances;
    }
}

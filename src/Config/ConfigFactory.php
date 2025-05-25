<?php

namespace TimeSeriesPhp\Config;

use TimeSeriesPhp\Exceptions\ConfigurationException;

class ConfigFactory
{
    private static array $configTypes = [
        'database' => DatabaseConfig::class,
        'connection' => ConnectionConfig::class,
        'multi_database' => MultiDatabaseConfig::class,
        'cache' => CacheConfig::class,
        'logging' => LoggingConfig::class,
        'performance' => PerformanceConfig::class
    ];

    public static function create(string $type, array $config = []): ConfigInterface
    {
        if (!isset(self::$configTypes[$type])) {
            throw new ConfigurationException("Unknown configuration type: {$type}");
        }

        $className = self::$configTypes[$type];
        return new $className($config);
    }

    public static function registerConfigType(string $type, string $className): void
    {
        if (!is_subclass_of($className, ConfigInterface::class)) {
            throw new ConfigurationException("Config class must implement ConfigInterface");
        }

        self::$configTypes[$type] = $className;
    }

    public static function getAvailableTypes(): array
    {
        return array_keys(self::$configTypes);
    }

    public static function createFromArray(array $configs): array
    {
        $instances = [];

        foreach ($configs as $type => $config) {
            $instances[$type] = self::create($type, $config);
        }

        return $instances;
    }
}

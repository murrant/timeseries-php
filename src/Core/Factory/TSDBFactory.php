<?php

namespace TimeSeriesPhp\Core\Factory;

use ReflectionClass;
use TimeSeriesPhp\Contracts\Config\ConfigInterface;
use TimeSeriesPhp\Contracts\Driver\TimeSeriesInterface;
use TimeSeriesPhp\Core\Attributes\Driver;
use TimeSeriesPhp\Exceptions\Driver\DriverException;

/**
 * Non-static version of DriverManager that can be injected as a dependency.
 * This addresses the issue of testability and dependency injection.
 */
class TSDBFactory
{
    /** @var array<string, class-string> */
    private array $drivers = [];

    /** @var array<string, class-string> */
    private array $configClasses = [];

    /**
     * Register a driver with the factory
     *
     * @param  class-string  $className  The fully qualified class name of the driver
     * @param  string|null  $name  The name of the driver (optional, will be inferred from Driver attribute if not provided)
     * @param  class-string|null  $configClassName  The fully qualified class name of the config class (optional, will be inferred from Driver attribute if not provided)
     *
     * @throws DriverException If the class doesn't exist or doesn't implement TimeSeriesInterface
     */
    public function registerDriver(string $className, ?string $name = null, ?string $configClassName = null): void
    {
        if (! class_exists($className)) {
            throw new DriverException("Driver class '{$className}' does not exist");
        }

        if (! is_subclass_of($className, TimeSeriesInterface::class)) {
            throw new DriverException("Driver class '{$className}' must implement TimeSeriesInterface");
        }

        // If name or config class name is not provided, try to get them from the Driver attribute
        if ($name === null || $configClassName === null) {
            $attributeInfo = $this->getDriverAttributeInfo($className);

            if ($name === null) {
                if ($attributeInfo === null) {
                    throw new DriverException("Driver name must be provided if the class doesn't have a Driver attribute");
                }
                $name = $attributeInfo['name'];
            }

            if ($configClassName === null && $attributeInfo !== null && $attributeInfo['configClass'] !== null) {
                $configClassName = $attributeInfo['configClass'];
            }
        }

        // If config class name is still not provided, throw an exception
        if ($configClassName === null) {
            throw new DriverException('Config class name must be provided or specified in the Driver attribute');
        }

        if (! class_exists($configClassName)) {
            throw new DriverException("Config class '{$configClassName}' does not exist");
        }

        if (! is_subclass_of($configClassName, ConfigInterface::class)) {
            throw new DriverException("Config class '{$configClassName}' must implement ConfigInterface");
        }

        $this->drivers[$name] = $className;
        $this->configClasses[$name] = $configClassName;
    }

    /**
     * Register default drivers
     *
     * @throws DriverException If a driver class doesn't exist or doesn't implement TimeSeriesInterface
     */
    public function registerDefaultDrivers(): void
    {
        // Register drivers using attributes
        $this->registerDriversFromAttributes();
    }

    /**
     * Register drivers using the Driver attribute
     *
     * @throws DriverException If a driver class doesn't exist or doesn't implement TimeSeriesInterface
     */
    public function registerDriversFromAttributes(): void
    {
        // Get all driver classes in the Drivers namespace
        $driverClasses = $this->getDriverClasses();

        foreach ($driverClasses as $driverClass) {
            // Skip if the class doesn't exist
            if (! class_exists($driverClass)) {
                continue;
            }

            // Skip if the class doesn't implement TimeSeriesInterface
            if (! is_subclass_of($driverClass, TimeSeriesInterface::class)) {
                continue;
            }

            // Check if the class has the Driver attribute
            $reflectionClass = new ReflectionClass($driverClass);
            $attributes = $reflectionClass->getAttributes(Driver::class);

            if (empty($attributes)) {
                continue;
            }

            // Get the attribute instance
            $attribute = $attributes[0]->newInstance();

            // Register the driver
            $this->registerDriver($driverClass);
        }
    }

    /**
     * Get all driver classes in the Drivers namespace
     *
     * @return array<int, class-string> Array of driver class names
     */
    private function getDriverClasses(): array
    {
        $driverClasses = [];

        // Define the driver namespace and directory
        $namespace = 'TimeSeriesPhp\\Drivers';
        $directory = __DIR__.'/../../Drivers';

        // Scan the directory for driver classes
        $this->scanDirectory($directory, $namespace, $driverClasses);

        return $driverClasses;
    }

    /**
     * Recursively scan a directory for PHP classes
     *
     * @param  string  $directory  The directory to scan
     * @param  string  $namespace  The namespace prefix for classes in this directory
     * @param  array<int, class-string>  &$classes  Array to store found class names
     */
    private function scanDirectory(string $directory, string $namespace, array &$classes): void
    {
        if (! is_dir($directory)) {
            return;
        }

        $files = scandir($directory);
        if ($files === false) {
            return;
        }

        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $path = $directory.'/'.$file;

            if (is_dir($path)) {
                // Recursively scan subdirectories
                $this->scanDirectory($path, $namespace.'\\'.$file, $classes);
            } elseif (pathinfo($file, PATHINFO_EXTENSION) === 'php') {
                // Get the class name from the file name
                $className = $namespace.'\\'.pathinfo($file, PATHINFO_FILENAME);

                // Add the class to the list if it exists
                if (class_exists($className)) {
                    /** @var class-string $className */
                    $classes[] = $className;
                }
            }
        }
    }

    /**
     * Get driver information from the Driver attribute
     *
     * @param  class-string  $className  The fully qualified class name of the driver
     * @return array{name: string, configClass: ?class-string}|null The driver name and config class, or null if the class doesn't have a Driver attribute
     */
    private function getDriverAttributeInfo(string $className): ?array
    {
        $reflectionClass = new ReflectionClass($className);
        $attributes = $reflectionClass->getAttributes(Driver::class);

        if (empty($attributes)) {
            return null;
        }

        $attribute = $attributes[0]->newInstance();

        /** @var class-string|null $configClass */
        $configClass = $attribute->configClass;

        return [
            'name' => $attribute->name,
            'configClass' => $configClass,
        ];
    }

    /**
     * Unregister a driver from the factory
     *
     * @param  string  $name  The name of the driver to unregister
     * @return bool True if the driver was unregistered, false if it wasn't registered
     */
    public function unregisterDriver(string $name): bool
    {
        if (isset($this->drivers[$name])) {
            unset($this->drivers[$name]);
            unset($this->configClasses[$name]);

            return true;
        }

        return false;
    }

    /**
     * Create a new instance of a time series database driver
     *
     * @param  string  $driver  The name of the driver to create
     * @param  ConfigInterface|null  $config  The configuration for the driver (optional)
     * @param  bool  $autoConnect  Whether to automatically connect to the database
     * @return TimeSeriesInterface A new instance of the driver
     *
     * @throws DriverException If the driver is not registered or doesn't implement TimeSeriesInterface
     */
    public function create(string $driver, ?ConfigInterface $config = null, bool $autoConnect = true): TimeSeriesInterface
    {
        if (! isset($this->drivers[$driver])) {
            throw new DriverException("Driver '{$driver}' not registered");
        }

        $className = $this->drivers[$driver];
        $instance = new $className;

        if (! $instance instanceof TimeSeriesInterface) {
            throw new DriverException("Driver '{$driver}' must implement TimeSeriesInterface");
        }

        if ($autoConnect) {
            // If no config is provided, create a default one
            if ($config === null) {
                $config = $this->createConfig($driver);
            }

            $instance->connect($config);
        }

        return $instance;
    }

    /**
     * Get a list of all registered drivers
     *
     * @return string[] Array of driver names
     */
    public function getAvailableDrivers(): array
    {
        return array_keys($this->drivers);
    }

    /**
     * Check if a driver is registered
     *
     * @param  string  $name  The name of the driver to check
     * @return bool True if the driver is registered, false otherwise
     */
    public function hasDriver(string $name): bool
    {
        return isset($this->drivers[$name]);
    }

    /**
     * Get the config class for a driver
     *
     * @param  string  $name  The name of the driver
     * @return class-string|null The fully qualified class name of the config class, or null if the driver is not registered
     */
    public function getConfigClass(string $name): ?string
    {
        return $this->configClasses[$name] ?? null;
    }

    /**
     * Create a configuration instance for a driver
     *
     * @param  string  $driver  The name of the driver
     * @param  array<string, mixed>  $config  The configuration options
     * @return ConfigInterface A new instance of the driver's configuration class
     *
     * @throws DriverException If the driver is not registered or the config class is invalid
     */
    public function createConfig(string $driver, array $config = []): ConfigInterface
    {
        $configClass = $this->getConfigClass($driver);

        if ($configClass === null) {
            throw new DriverException("No configuration class registered for driver: {$driver}");
        }

        /** @var ConfigInterface */
        return new $configClass($config);
    }
}

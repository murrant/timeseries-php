<?php

declare(strict_types=1);

namespace TimeSeriesPhp\Core\Driver;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use TimeSeriesPhp\Core\Attributes\Config;

/**
 * Abstract base class for driver configuration
 */
abstract class AbstractDriverConfiguration implements ConfigurationInterface
{
    /**
     * Get the configuration tree builder
     *
     * @return TreeBuilder The tree builder
     */
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder($this->getConfigName());
        $rootNode = $treeBuilder->getRootNode();

        // Define the base configuration schema (none right now)

        // Allow drivers to extend the configuration schema
        $this->configureSchema($rootNode);

        return $treeBuilder;
    }

    /**
     * Get the configuration name from the Config attribute
     *
     * @return string The configuration name
     */
    protected function getConfigName(): string
    {
        $reflection = new \ReflectionClass(static::class);
        $attributes = $reflection->getAttributes(Config::class);

        if (empty($attributes)) {
            throw new \RuntimeException(sprintf('Configuration class %s must have a Config attribute', static::class));
        }

        /** @var Config $config */
        $config = $attributes[0]->newInstance();

        return $config->name;
    }

    /**
     * Configure the schema for this driver
     *
     * @param  \Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition  $rootNode  The root node
     */
    abstract protected function configureSchema(\Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition $rootNode): void;

    /**
     * Process the configuration
     *
     * @param  array<string, mixed>  $config  The configuration to process
     * @return array<string, mixed> The processed configuration
     */
    public function processConfiguration(array $config): array
    {
        $processor = new \Symfony\Component\Config\Definition\Processor;

        /** @var array<string, mixed> $processedConfig */
        $processedConfig = $processor->processConfiguration($this, [$config]);

        return $processedConfig;
    }

    /**
     * Create a new instance of this configuration class with the given configuration
     *
     * @param  array<string, mixed>  $config  The configuration to use
     * @return static A new instance of this configuration class
     */
    public function createFromArray(array $config): static
    {
        $processedConfig = $this->processConfiguration($config);

        // Use reflection to create a new instance with the processed configuration
        $reflection = new \ReflectionClass(static::class);
        $constructor = $reflection->getConstructor();

        if (! $constructor) {
            return $reflection->newInstance();
        }

        $parameters = $constructor->getParameters();
        $args = [];

        foreach ($parameters as $parameter) {
            $name = $parameter->getName();
            $args[$name] = $processedConfig[$name] ?? ($parameter->isDefaultValueAvailable() ? $parameter->getDefaultValue() : null);
        }

        return $reflection->newInstanceArgs($args);
    }
}

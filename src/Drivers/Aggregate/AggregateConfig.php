<?php

namespace TimeSeriesPhp\Drivers\Aggregate;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use TimeSeriesPhp\Core\Attributes\Config;
use TimeSeriesPhp\Core\Driver\AbstractDriverConfiguration;
use TimeSeriesPhp\Exceptions\Config\ConfigurationException;

/**
 * Configuration for the Aggregate driver
 */
#[Config('aggregate', AggregateDriver::class)]
class AggregateConfig extends AbstractDriverConfiguration
{
    /**
     * @param  array<int, array<string, mixed>>  $write_databases  Array of write database configurations
     * @param  array<string, mixed>|null  $read_database  Read database configuration
     */
    public function __construct(
        public readonly array $write_databases = [],
        public readonly ?array $read_database = null,
    ) {
        // Validate the databases
        if (empty($this->write_databases)) {
            throw new ConfigurationException('At least one write database must be configured');
        }

        foreach ($this->write_databases as $index => $config) {
            if (! is_array($config) || ! isset($config['driver'])) {
                throw new ConfigurationException("Driver not specified for write database at index {$index}");
            }
        }

        if ($this->read_database !== null && (! is_array($this->read_database) || ! isset($this->read_database['driver']))) {
            throw new ConfigurationException('Driver not specified for read database');
        }
    }

    /**
     * Configure the schema for this driver
     *
     * @param  ArrayNodeDefinition  $rootNode  The root node
     */
    protected function configureSchema(ArrayNodeDefinition $rootNode): void
    {
        $rootNode
            ->children()
            ->arrayNode('write_databases')
            ->info('Array of write database configurations')
            ->isRequired()
            ->requiresAtLeastOneElement()
            ->arrayPrototype()
            ->children()
            ->scalarNode('driver')
            ->info('The driver to use')
            ->isRequired()
            ->cannotBeEmpty()
            ->end()
            ->variableNode('config')
            ->info('Driver-specific configuration')
            ->end()
            ->end()
            ->end()
            ->end()
            ->arrayNode('read_database')
            ->info('Read database configuration')
            ->children()
            ->scalarNode('driver')
            ->info('The driver to use')
            ->isRequired()
            ->cannotBeEmpty()
            ->end()
            ->variableNode('config')
            ->info('Driver-specific configuration')
            ->end()
            ->end()
            ->end()
            ->end();
    }

    /**
     * Get all write database configurations
     *
     * @return array<int, array<string, mixed>>
     */
    public function getWriteDatabases(): array
    {
        return $this->write_databases;
    }

    /**
     * Get the read database configuration
     *
     * @return array<string, mixed>|null
     */
    public function getReadDatabase(): ?array
    {
        if ($this->read_database === null) {
            // If no read database is configured, use the first write database
            if (! empty($this->write_databases)) {
                return $this->write_databases[0];
            }

            return null;
        }

        return $this->read_database;
    }

    /**
     * Create a new instance with an added write database configuration
     *
     * @param  array<string, mixed>  $config
     *
     * @throws ConfigurationException
     */
    public function addWriteDatabase(array $config): self
    {
        if (! isset($config['driver'])) {
            throw new ConfigurationException('Driver not specified for write database');
        }

        $writeDatabases = $this->write_databases;
        $writeDatabases[] = $config;

        return new self($writeDatabases, $this->read_database);
    }

    /**
     * Create a new instance with the specified read database configuration
     *
     * @param  array<string, mixed>|null  $config
     *
     * @throws ConfigurationException
     */
    public function setReadDatabase(?array $config): self
    {
        if ($config !== null && ! isset($config['driver'])) {
            throw new ConfigurationException('Driver not specified for read database');
        }

        return new self($this->write_databases, $config);
    }
}

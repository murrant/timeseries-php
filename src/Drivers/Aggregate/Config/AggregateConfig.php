<?php

namespace TimeSeriesPhp\Drivers\Aggregate\Config;

use TimeSeriesPhp\Core\Attributes\Config;
use TimeSeriesPhp\Core\Config\AbstractConfig;
use TimeSeriesPhp\Drivers\Aggregate\AggregateDriver;
use TimeSeriesPhp\Exceptions\Config\ConfigurationException;

#[Config('aggregate', AggregateDriver::class)]
class AggregateConfig extends AbstractConfig
{
    protected array $defaults = [
        'write_databases' => [],
        'read_database' => null,
    ];

    protected array $required = ['write_databases'];

    public function __construct(array $config = [])
    {
        parent::__construct($config);
        $this->validateDatabases();
    }

    /**
     * @throws ConfigurationException
     */
    private function validateDatabases(): void
    {
        $writeDatabases = $this->getArray('write_databases');

        if (empty($writeDatabases)) {
            throw new ConfigurationException('At least one write database must be configured');
        }

        foreach ($writeDatabases as $index => $config) {
            if (! is_array($config) || ! isset($config['driver'])) {
                throw new ConfigurationException("Driver not specified for write database at index {$index}");
            }
        }

        $readDatabase = $this->get('read_database');
        if ($readDatabase !== null && (! is_array($readDatabase) || ! isset($readDatabase['driver']))) {
            throw new ConfigurationException('Driver not specified for read database');
        }
    }

    /**
     * Get all write database configurations
     *
     * @return array<int, array<string, mixed>>
     *
     * @throws ConfigurationException
     */
    public function getWriteDatabases(): array
    {
        $databases = $this->getArray('write_databases');

        /** @var array<int, array<string, mixed>> $databases */
        return $databases;
    }

    /**
     * Get the read database configuration
     *
     * @return array<string, mixed>|null
     *
     * @throws ConfigurationException
     */
    public function getReadDatabase(): ?array
    {
        $readDatabase = $this->get('read_database');

        if ($readDatabase === null) {
            // If no read database is configured, use the first write database
            $writeDatabases = $this->getWriteDatabases();
            if (! empty($writeDatabases)) {
                return $writeDatabases[0];
            }

            return null;
        }

        if (! is_array($readDatabase)) {
            throw new ConfigurationException('Read database configuration must be an array');
        }

        /** @var array<string, mixed> $readDatabase */
        return $readDatabase;
    }

    /**
     * Add a write database configuration
     *
     * @param  array<string, mixed>  $config
     * @return $this
     *
     * @throws ConfigurationException
     */
    public function addWriteDatabase(array $config): self
    {
        if (! isset($config['driver'])) {
            throw new ConfigurationException('Driver not specified for write database');
        }

        $writeDatabases = $this->getArray('write_databases');
        $writeDatabases[] = $config;
        $this->set('write_databases', $writeDatabases);

        return $this;
    }

    /**
     * Set the read database configuration
     *
     * @param  array<string, mixed>|null  $config
     * @return $this
     *
     * @throws ConfigurationException
     */
    public function setReadDatabase(?array $config): self
    {
        if ($config !== null && ! isset($config['driver'])) {
            throw new ConfigurationException('Driver not specified for read database');
        }

        $this->set('read_database', $config);

        return $this;
    }
}

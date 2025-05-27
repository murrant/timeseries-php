<?php

namespace TimeSeriesPhp\Config;

use TimeSeriesPhp\Exceptions\ConfigurationException;

abstract class AbstractConfig implements ConfigInterface
{
    /**
     * @var array<string, mixed>
     */
    protected array $config = [];

    /**
     * @var string[]
     */
    protected array $required = [];

    /**
     * @var array<string, mixed>
     */
    protected array $defaults = [];

    /**
     * @var array<string, callable>
     */
    protected array $validators = [];

    /**
     * @param  array<string, mixed>  $config
     *
     * @throws ConfigurationException
     */
    public function __construct(array $config = [])
    {
        $this->config = array_merge($this->getDefaults(), $config);
        $this->validate();
    }

    /**
     * @return array<string, mixed>
     */
    protected function getDefaults(): array
    {
        return $this->defaults;
    }

    /**
     * @return string[]
     */
    protected function getRequired(): array
    {
        return $this->required;
    }

    /**
     * @throws ConfigurationException
     */
    public function validate(): bool
    {
        // Check required fields
        foreach ($this->getRequired() as $field) {
            if (! $this->has($field)) {
                throw new ConfigurationException("Required configuration field '{$field}' is missing");
            }
        }

        // Run custom validators
        foreach ($this->validators as $field => $validator) {
            if ($this->has($field)) {
                $value = $this->get($field);
                if (! $validator($value)) {
                    throw new ConfigurationException("Invalid value for configuration field '{$field}'");
                }
            }
        }

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->config;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->config[$key] ?? $default;
    }

    public function set(string $key, mixed $value): ConfigInterface
    {
        $this->config[$key] = $value;

        return $this;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->config);
    }

    /**
     * @param  array<string, mixed>  $config
     *
     * @throws ConfigurationException
     */
    public function merge(array $config): ConfigInterface
    {
        $this->config = array_merge($this->config, $config);
        $this->validate();

        return $this;
    }

    protected function addValidator(string $field, callable $validator): void
    {
        $this->validators[$field] = $validator;
    }
}

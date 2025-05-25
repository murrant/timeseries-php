<?php

namespace TimeSeriesPhp\Config;

use TimeSeriesPhp\Config\ConfigInterface;
use TimeSeriesPhp\Exceptions\ConfigurationException;

abstract class AbstractConfig implements ConfigInterface
{
    protected array $config = [];
    protected array $required = [];
    protected array $defaults = [];
    protected array $validators = [];

    public function __construct(array $config = [])
    {
        $this->config = array_merge($this->getDefaults(), $config);
        $this->validate();
    }

    protected function getDefaults(): array
    {
        return $this->defaults;
    }

    protected function getRequired(): array
    {
        return $this->required;
    }

    public function validate(): bool
    {
        // Check required fields
        foreach ($this->getRequired() as $field) {
            if (!$this->has($field)) {
                throw new ConfigurationException("Required configuration field '{$field}' is missing");
            }
        }

        // Run custom validators
        foreach ($this->validators as $field => $validator) {
            if ($this->has($field)) {
                $value = $this->get($field);
                if (!$validator($value)) {
                    throw new ConfigurationException("Invalid value for configuration field '{$field}'");
                }
            }
        }

        return true;
    }

    public function toArray(): array
    {
        return $this->config;
    }

    public function get(string $key, $default = null)
    {
        return $this->config[$key] ?? $default;
    }

    public function set(string $key, $value): ConfigInterface
    {
        $this->config[$key] = $value;
        return $this;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->config);
    }

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

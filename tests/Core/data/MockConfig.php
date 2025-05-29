<?php

namespace TimeSeriesPhp\Tests\Core\data;

use TimeSeriesPhp\Config\ConfigInterface;

class MockConfig implements ConfigInterface
{
    public function get(string $key, mixed $default = null): mixed
    {
        return $default;
    }

    public function set(string $key, mixed $value): ConfigInterface
    {
        return $this;
    }

    public function has(string $key): bool
    {
        return false;
    }

    public function toArray(): array
    {
        return [];
    }

    public function merge(array $config): ConfigInterface
    {
        return $this;
    }

    public function validate(): bool
    {
        return true;
    }

    public function getString(string $key): string
    {
        return '';
    }

    public function getInt(string $key): int
    {
        return 0;
    }

    public function getFloat(string $key): float
    {
        return 0.0;
    }

    public function getBool(string $key): bool
    {
        return false;
    }

    public function getArray(string $key): array
    {
        return [];
    }
}

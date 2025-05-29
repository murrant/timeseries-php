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

    public function getString(string $key, ?string $default = null): string
    {
        return $default ?? '';
    }

    public function getInt(string $key, ?int $default = null): int
    {
        return $default ?? 0;
    }

    public function getFloat(string $key, ?float $default = null): float
    {
        return $default ?? 0.0;
    }

    public function getBool(string $key, ?bool $default = null): bool
    {
        return $default ?? false;
    }

    public function getArray(string $key, ?array $default = null): array
    {
        return $default ?? [];
    }
}

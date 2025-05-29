<?php

namespace TimeSeriesPhp\Config;

interface ConfigInterface
{
    public function validate(): bool;

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array;

    public function get(string $key, mixed $default = null): mixed;

    public function getString(string $key, ?string $default = null): string;

    public function getInt(string $key, ?int $default = null): int;

    public function getFloat(string $key, ?float $default = null): float;

    public function getBool(string $key, ?bool $default = null): bool;

    /**
     * @param string $key
     * @param array<mixed, mixed>|null $default
     * @return array<mixed, mixed>
     */
    public function getArray(string $key, ?array $default = null): array;

    public function set(string $key, mixed $value): self;

    public function has(string $key): bool;

    /**
     * @param  array<string, mixed>  $config
     */
    public function merge(array $config): self;
}

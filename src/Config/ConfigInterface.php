<?php

namespace TimeSeriesPhp\Config;

use DateTime;

interface ConfigInterface
{
    public function validate(): bool;

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array;

    public function get(string $key, mixed $default = null): mixed;

    public function getString(string $key): string;
    public function getInt(string $key): int;
    public function getFloat(string $key): float;
    public function getBool(string $key): bool;

    /**
     * @return array<mixed, mixed>
     */
    public function getArray(string $key): array;

    public function set(string $key, mixed $value): self;

    public function has(string $key): bool;

    /**
     * @param  array<string, mixed>  $config
     */
    public function merge(array $config): self;
}

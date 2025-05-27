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

    public function set(string $key, mixed $value): self;

    public function has(string $key): bool;

    /**
     * @param  array<string, mixed>  $config
     */
    public function merge(array $config): self;
}

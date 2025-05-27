<?php

namespace TimeSeriesPhp\Config;

interface ConfigInterface
{
    public function validate(): bool;

    public function toArray(): array;

    public function get(string $key, $default = null);

    public function set(string $key, $value): self;

    public function has(string $key): bool;

    public function merge(array $config): self;
}

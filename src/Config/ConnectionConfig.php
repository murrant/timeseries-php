<?php

namespace TimeSeriesPhp\Config;

class ConnectionConfig implements ConfigInterface
{
    public function __construct(
        private readonly array $config
    ) {
    }

    public function get(string $key, $default = null): mixed
    {
        return $this->config[$key] ?? $default;
    }
}

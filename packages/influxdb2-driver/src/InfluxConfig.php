<?php

namespace TimeseriesPhp\Driver\InfluxDB2;

final readonly class InfluxConfig
{
    public function __construct(
        public string $host,
        public int $port = 8086,
        public string $token = '',
        public string $org = '',
        public string $bucket = '',
    ) {}

    public static function fromArray(array $config): self
    {
        return new self(...$config);
    }
}

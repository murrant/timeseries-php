<?php

namespace TimeseriesPhp\Driver\InfluxDB2;

use TimeseriesPhp\Core\Contracts\TsdbConfig;

final readonly class InfluxConfig implements TsdbConfig
{
    public function __construct(
        public string $host,
        public int $port = 8086,
        public string $token = '',
        public string $org = '',
        public string $bucket = '',
    ) {}

    /**
     * @param  array{host: string, port: int, token: string, org: string, bucket: string}  $config
     */
    public static function fromArray(array $config): self
    {
        return new self(...$config);
    }
}

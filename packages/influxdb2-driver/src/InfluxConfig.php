<?php

namespace TimeseriesPhp\Driver\InfluxDB2;

use TimeseriesPhp\Core\Contracts\DriverConfig;
use TimeseriesPhp\Core\Exceptions\TimeseriesException;

final readonly class InfluxConfig implements DriverConfig
{
    public function __construct(
        public string $host,
        public int $port = 8086,
        public string $token = '',
        public string $org = '',
        public string $bucket = '',
    ) {}

    /**
     * @param  DriverConfig|array<string, mixed>  $config
     *
     * @throws TimeseriesException
     */
    public static function make(DriverConfig|array $config = []): self
    {
        if (is_array($config)) {
            return self::fromArray($config);
        }

        if (! $config instanceof self) {
            throw new TimeseriesException('Invalid configuration type');
        }

        return $config;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public static function fromArray(array $config): self
    {
        return new self(
            host: $config['host'],
            port: $config['port'] ?? 8086,
            token: $config['token'] ?? '',
            org: $config['org'] ?? '',
            bucket: $config['bucket'] ?? '',
        );
    }
}

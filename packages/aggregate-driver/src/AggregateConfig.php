<?php

declare(strict_types=1);

namespace TimeseriesPhp\Driver\Aggregate;

use TimeseriesPhp\Core\Contracts\DriverConfig;

final readonly class AggregateConfig implements DriverConfig
{
    /**
     * @param  string[]  $connections
     */
    public function __construct(
        public array $connections,
    ) {}

    public static function make(array|DriverConfig $config = []): self
    {
        if ($config instanceof self) {
            return $config;
        }

        if (is_array($config)) {
            return new self(
                connections: $config['connections'] ?? [],
            );
        }

        throw new \InvalidArgumentException('Invalid configuration provided for Aggregate driver');
    }
}

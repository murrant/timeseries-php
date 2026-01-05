<?php

declare(strict_types=1);

namespace TimeseriesPhp\Driver\Null;

use TimeseriesPhp\Core\Contracts\DriverConfig;

final readonly class NullConfig implements DriverConfig
{
    public static function make(array|DriverConfig $config = []): self
    {
        if (is_array($config)) {
            return self::fromArray($config);
        }

        if (! $config instanceof self) {
            throw new \InvalidArgumentException('Invalid configuration type');
        }

        return $config;
    }

    public static function fromArray(array $config): self
    {
        return new self;
    }
}

<?php

namespace TimeseriesPhp\Core\Metrics;

use TimeseriesPhp\Core\Enum\MetricType;

final readonly class MetricIdentifier
{
    public function __construct(
        public string $namespace,
        public string $name,
        public ?string $unit = null,
        public ?MetricType $type = null,
    ) {}
}

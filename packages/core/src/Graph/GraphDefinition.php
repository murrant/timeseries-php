<?php

namespace TimeseriesPhp\Core\Graph;

final readonly class GraphDefinition
{
    public function __construct(
        public readonly TimeRange $range,
        public readonly Resolution $resolution,
        /** @var SeriesDefinition[] */
        public readonly array $series,
        public readonly GraphStyle $style,
    ) {}
}

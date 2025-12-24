<?php

namespace TimeseriesPhp\Core\Graph;

use TimeseriesPhp\Core\Time\TimeRange;
use TimeseriesPhp\Core\Timeseries\Resolution;
use TimeseriesPhp\Core\Timeseries\SeriesDefinition;

final readonly class GraphDefinition
{
    public function __construct(
        public TimeRange $range,
        public Resolution $resolution,
        /** @var SeriesDefinition[] */
        public array $series,
        public GraphStyle $style,
        /** @var array<string, mixed> */
        public array $extensions = [],
    ) {}
}

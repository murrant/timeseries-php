<?php

namespace TimeseriesPhp\Core\Graph;

final readonly class GraphStyle
{
    public function __construct(
        public string $title,
        public string $unit,
        public bool $stacked = false,
    ) {}
}

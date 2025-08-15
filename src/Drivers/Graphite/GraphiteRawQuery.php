<?php

declare(strict_types=1);

namespace TimeSeriesPhp\Drivers\Graphite;

use TimeSeriesPhp\Contracts\Query\RawQueryInterface;

/**
 * Graphite Raw Query implementation
 *
 * This represents a raw query for Graphite's render API.
 * The driver will be responsible for prepending the correct endpoint and
 * handling any required transport details.
 */
class GraphiteRawQuery implements RawQueryInterface
{
    public function __construct(private string $rawQuery) {}

    public function getRawQuery(): string
    {
        return $this->rawQuery;
    }
}

<?php

declare(strict_types=1);

namespace TimeSeriesPhp\Drivers\Prometheus;

use TimeSeriesPhp\Contracts\Query\RawQueryInterface;

/**
 * Prometheus Raw Query implementation
 *
 * This represents a raw query string (e.g., "query=up" or range query params)
 * that the Prometheus driver understands.
 */
class PrometheusRawQuery implements RawQueryInterface
{
    public function __construct(private string $rawQuery)
    {
    }

    public function getRawQuery(): string
    {
        return $this->rawQuery;
    }
}

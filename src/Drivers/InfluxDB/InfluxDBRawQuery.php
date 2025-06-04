<?php

namespace TimeSeriesPhp\Drivers\InfluxDB;

use TimeSeriesPhp\Contracts\Query\RawQueryInterface;

/**
 * InfluxDB Raw Query implementation
 *
 * This class represents a raw query for InfluxDB, which can be either Flux or InfluxQL.
 */
class InfluxDBRawQuery implements RawQueryInterface
{
    /**
     * Create a new InfluxDB raw query
     *
     * @param  string  $rawQuery  The raw query string (Flux or InfluxQL)
     * @param  bool  $isFlux  Whether the query is Flux (true) or InfluxQL (false)
     */
    public function __construct(
        protected string $rawQuery,
        public readonly bool $isFlux = true,
    ) {}

    /**
     * Get the raw query string
     *
     * @return string The raw query string
     */
    public function getRawQuery(): string
    {
        return $this->rawQuery;
    }
}

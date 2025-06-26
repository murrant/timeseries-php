<?php

namespace TimeSeriesPhp\Drivers\InfluxDB\Connection\Command;

/**
 * Command for querying data from InfluxDB
 */
class QueryCommand extends AbstractInfluxDBHttpCommand
{
    /**
     * Get the HTTP endpoint for this command
     *
     * @return string The endpoint URL path
     */
    public function getEndpoint(): string
    {
        return '/api/v2/query';
    }

    /**
     * Get the HTTP method for this command
     *
     * @return string The HTTP method (GET, POST, etc.)
     */
    public function getMethod(): string
    {
        return 'POST';
    }
}

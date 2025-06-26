<?php

namespace TimeSeriesPhp\Drivers\InfluxDB\Connection\Command\V1;

use TimeSeriesPhp\Drivers\InfluxDB\Connection\Command\AbstractInfluxDBHttpCommand;

/**
 * Command for querying data from InfluxDB API v1
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
        return '/query';
    }

    /**
     * Get the HTTP method for this command
     *
     * @return string The HTTP method (GET, POST, etc.)
     */
    public function getMethod(): string
    {
        return 'GET';
    }

    /**
     * Get the query parameters for this command
     *
     * @param  string  $orgId  The organization ID (not used in v1)
     * @param  string  $bucket  The bucket name (database in v1)
     * @param  string  $precision  The precision
     * @return array<string, string> The query parameters
     */
    public function getQueryParams(string $orgId, string $bucket, string $precision): array
    {
        return [
            'db' => $bucket,
            'epoch' => $precision,
        ];
    }
}

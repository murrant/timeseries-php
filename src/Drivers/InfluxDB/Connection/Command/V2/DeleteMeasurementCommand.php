<?php

namespace TimeSeriesPhp\Drivers\InfluxDB\Connection\Command\V2;

use TimeSeriesPhp\Drivers\InfluxDB\Connection\Command\AbstractInfluxDBHttpCommand;

/**
 * Command for deleting a measurement in InfluxDB (API v2)
 */
class DeleteMeasurementCommand extends AbstractInfluxDBHttpCommand
{
    /**
     * Get the HTTP endpoint for this command
     *
     * @return string The endpoint URL path
     */
    public function getEndpoint(): string
    {
        return '/api/v2/delete';
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

    /**
     * Get the query parameters for this command
     *
     * @param  string  $orgId  The organization ID
     * @param  string  $bucket  The bucket name
     * @param  string  $precision  The precision
     * @return array<string, string> The query parameters
     */
    public function getQueryParams(string $orgId, string $bucket, string $precision): array
    {
        return [
            'org' => $orgId,
            'bucket' => $bucket,
        ];
    }
}

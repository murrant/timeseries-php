<?php

namespace TimeSeriesPhp\Drivers\InfluxDB\Connection\Command\V1;

use TimeSeriesPhp\Drivers\InfluxDB\Connection\Command\AbstractInfluxDBHttpCommand;

/**
 * Command for getting databases from InfluxDB (API v1)
 * In InfluxDB v1, buckets are called databases
 */
class GetBucketsCommand extends AbstractInfluxDBHttpCommand
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
     * @param  string  $bucket  The bucket name (not used for this command)
     * @param  string  $precision  The precision (not used for this command)
     * @return array<string, string> The query parameters
     */
    public function getQueryParams(string $orgId, string $bucket, string $precision): array
    {
        return [
            'q' => 'SHOW DATABASES',
        ];
    }

    /**
     * Process the data for this command
     * Convert the v1 response format to match the v2 format
     *
     * @param  string  $data  The input data
     * @param  string  $orgId  The organization ID
     * @return string The processed data
     */
    public function processData(string $data, string $orgId): string
    {
        // For v1, we don't need to process the input data
        return $data;
    }
}

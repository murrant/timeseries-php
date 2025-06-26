<?php

namespace TimeSeriesPhp\Drivers\InfluxDB\Connection\Command;

/**
 * Command for writing data to InfluxDB
 */
class WriteCommand extends AbstractInfluxDBHttpCommand
{
    /**
     * Get the HTTP endpoint for this command
     *
     * @return string The endpoint URL path
     */
    public function getEndpoint(): string
    {
        return '/api/v2/write';
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
            'precision' => $precision,
        ];
    }
}

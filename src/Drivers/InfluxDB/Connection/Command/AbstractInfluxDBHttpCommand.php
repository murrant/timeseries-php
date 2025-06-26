<?php

namespace TimeSeriesPhp\Drivers\InfluxDB\Connection\Command;

/**
 * Base class for InfluxDB HTTP commands
 */
abstract class AbstractInfluxDBHttpCommand implements InfluxDBHttpCommand
{
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
        return [];
    }

    /**
     * Process the data for this command
     *
     * @param  string  $data  The input data
     * @param  string  $orgId  The organization ID
     * @return string The processed data
     */
    public function processData(string $data, string $orgId): string
    {
        return $data;
    }
}

<?php

namespace TimeSeriesPhp\Drivers\InfluxDB\Connection\Command\V1;

use TimeSeriesPhp\Drivers\InfluxDB\Connection\Command\AbstractInfluxDBHttpCommand;

/**
 * Command for creating a database in InfluxDB (API v1)
 * In InfluxDB v1, buckets are called databases
 */
class CreateBucketCommand extends AbstractInfluxDBHttpCommand
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
        return 'POST';
    }

    /**
     * Get the query parameters for this command
     *
     * @param  string  $orgId  The organization ID (not used in v1)
     * @param  string  $bucket  The bucket name (database in v1)
     * @param  string  $precision  The precision (not used for this command)
     * @return array<string, string> The query parameters
     */
    public function getQueryParams(string $orgId, string $bucket, string $precision): array
    {
        return [];
    }

    /**
     * Process the data for this command
     *
     * @param  string  $data  The input data (JSON with bucket name)
     * @param  string  $orgId  The organization ID (not used in v1)
     * @return string The processed data
     */
    public function processData(string $data, string $orgId): string
    {
        $decodedData = json_decode($data, true);
        if (! is_array($decodedData) || ! isset($decodedData['name'])) {
            return 'q=CREATE DATABASE "default"';
        }

        // Ensure name is a string
        $dbName = is_string($decodedData['name'])
            ? $decodedData['name']
            : (is_scalar($decodedData['name']) ? (string) $decodedData['name'] : 'default');

        // Escape the database name for the query
        $escapedName = str_replace('"', '\"', $dbName);

        return 'q=CREATE DATABASE "'.$escapedName.'"';
    }
}

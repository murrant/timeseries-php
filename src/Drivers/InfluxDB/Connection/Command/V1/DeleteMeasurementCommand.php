<?php

namespace TimeSeriesPhp\Drivers\InfluxDB\Connection\Command\V1;

use TimeSeriesPhp\Drivers\InfluxDB\Connection\Command\AbstractInfluxDBHttpCommand;

/**
 * Command for deleting a measurement in InfluxDB (API v1)
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
        return [
            'db' => $bucket,
        ];
    }

    /**
     * Process the data for this command
     *
     * @param  string  $data  The input data (JSON with measurement name and predicate)
     * @param  string  $orgId  The organization ID (not used in v1)
     * @return string The processed data
     */
    public function processData(string $data, string $orgId): string
    {
        $decodedData = json_decode($data, true);
        if (! is_array($decodedData) || ! isset($decodedData['measurement'])) {
            return 'q=DROP MEASUREMENT "unknown"';
        }

        $measurement = (string) $decodedData['measurement'];
        // Escape the measurement name for the query
        $escapedName = str_replace('"', '\"', $measurement);

        return 'q=DROP MEASUREMENT "'.$escapedName.'"';
    }
}

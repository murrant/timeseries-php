<?php

namespace TimeSeriesPhp\Drivers\InfluxDB\Connection\Command;

/**
 * Command for creating a bucket in InfluxDB
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
        return '/api/v2/buckets';
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
     * Process the data for this command
     *
     * @param  string  $data  The input data
     * @param  string  $orgId  The organization ID
     * @return string The processed data
     */
    public function processData(string $data, string $orgId): string
    {
        $decodedData = json_decode($data, true);
        if (! is_array($decodedData)) {
            $decodedData = [];
        }
        $decodedData['orgID'] = $orgId;
        $encoded = json_encode($decodedData);
        if ($encoded === false) {
            return '{}';
        }

        return $encoded;
    }
}

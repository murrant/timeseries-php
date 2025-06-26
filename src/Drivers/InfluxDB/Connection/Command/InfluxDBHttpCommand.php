<?php

namespace TimeSeriesPhp\Drivers\InfluxDB\Connection\Command;

/**
 * Interface for InfluxDB HTTP commands
 */
interface InfluxDBHttpCommand
{
    /**
     * Get the HTTP endpoint for this command
     *
     * @return string The endpoint URL path
     */
    public function getEndpoint(): string;

    /**
     * Get the HTTP method for this command
     *
     * @return string The HTTP method (GET, POST, etc.)
     */
    public function getMethod(): string;

    /**
     * Get additional HTTP headers
     *
     * @return array<string, string>
     */
    public function getHeaders(): array;

    /**
     * Get the query parameters for this command
     *
     * @param  string  $orgId  The organization ID
     * @param  string  $bucket  The bucket name
     * @param  string  $precision  The precision
     * @return array<string, string> The query parameters
     */
    public function getQueryParams(string $orgId, string $bucket, string $precision): array;

    /**
     * Process the data for this command
     *
     * @param  string  $data  The input data
     * @param  string  $orgId  The organization ID
     * @return string The processed data
     */
    public function processData(string $data, string $orgId): string;
}

<?php

namespace TimeSeriesPhp\Drivers\InfluxDB\Connection\Command\V2;

use TimeSeriesPhp\Drivers\InfluxDB\Connection\Command\AbstractInfluxDBHttpCommand;

/**
 * Command for querying data from InfluxDB API v2
 */
class QueryCommand extends AbstractInfluxDBHttpCommand
{
    /** {@inheritDoc} */
    public function getEndpoint(): string
    {
        return '/api/v2/query';
    }

    /** {@inheritDoc} */
    public function getMethod(): string
    {
        return 'POST';
    }

    /** {@inheritDoc} */
    public function getHeaders(): array
    {
        return [
            'Content-Type' => 'application/vnd.flux',
            'Accept' => 'application/csv',
        ];
    }

    /** {@inheritDoc} */
    public function getQueryParams(string $orgId, string $bucket, string $precision): array
    {
        return [
            'orgID' => $orgId,
        ];
    }
}

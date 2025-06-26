<?php

namespace TimeSeriesPhp\Drivers\InfluxDB\Connection\Command\V2;

use TimeSeriesPhp\Drivers\InfluxDB\Connection\Command\AbstractInfluxDBHttpCommand;

/**
 * Command for getting buckets from InfluxDB (API v2)
 */
class GetBucketsCommand extends AbstractInfluxDBHttpCommand
{
    /** {@inheritDoc} */
    public function getEndpoint(): string
    {
        return '/api/v2/buckets';
    }

    /** {@inheritDoc} */
    public function getMethod(): string
    {
        return 'GET';
    }

    /** {@inheritDoc} */
    public function getQueryParams(string $orgId, string $bucket, string $precision): array
    {
        return [
            'orgID' => $orgId,
        ];
    }
}

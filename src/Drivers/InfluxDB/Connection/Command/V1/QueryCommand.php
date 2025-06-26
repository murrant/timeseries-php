<?php

namespace TimeSeriesPhp\Drivers\InfluxDB\Connection\Command\V1;

use TimeSeriesPhp\Drivers\InfluxDB\Connection\Command\AbstractInfluxDBHttpCommand;

/**
 * Command for querying data from InfluxDB API v1
 */
class QueryCommand extends AbstractInfluxDBHttpCommand
{
    private string $data = '';

    /** @inheritDoc */
    public function getEndpoint(): string
    {
        return '/query';
    }

    /** @inheritDoc */
    public function getMethod(): string
    {
        return 'GET';
    }

    /** @inheritDoc */
    public function getQueryParams(string $orgId, string $bucket, string $precision): array
    {
        return [
            'db' => $bucket,
            'q' => $this->data,
        ];
    }

    /** @inheritDoc */
    public function processData(string $data, string $orgId): string
    {
        $this->data = $data;

        return '';
    }
}

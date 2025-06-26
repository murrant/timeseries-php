<?php

namespace TimeSeriesPhp\Drivers\InfluxDB\Connection\Command;

/**
 * Base class for InfluxDB HTTP commands
 */
abstract class AbstractInfluxDBHttpCommand implements InfluxDBHttpCommand
{
    public function getQueryParams(string $orgId, string $bucket, string $precision): array
    {
        return [];
    }

    public function processData(string $data, string $orgId): string
    {
        return $data;
    }

    public function getHeaders(): array
    {
        return [];
    }
}

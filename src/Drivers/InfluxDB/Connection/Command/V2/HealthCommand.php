<?php

namespace TimeSeriesPhp\Drivers\InfluxDB\Connection\Command\V2;

use TimeSeriesPhp\Drivers\InfluxDB\Connection\Command\AbstractInfluxDBHttpCommand;

/**
 * Command for checking InfluxDB health (API v2)
 */
class HealthCommand extends AbstractInfluxDBHttpCommand
{
    /**
     * Get the HTTP endpoint for this command
     *
     * @return string The endpoint URL path
     */
    public function getEndpoint(): string
    {
        return '/health';
    }

    /**
     * Get the HTTP method for this command
     *
     * @return string The HTTP method (GET, POST, etc.)
     */
    public function getMethod(): string
    {
        return 'GET';
    }
}

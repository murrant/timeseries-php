<?php

namespace TimeSeriesPhp\Drivers\InfluxDB\Connection\Command;

use TimeSeriesPhp\Exceptions\Driver\ConnectionException;

/**
 * Factory for creating InfluxDB HTTP commands
 */
class InfluxDBHttpCommandFactory
{
    /**
     * Create a command object for the given command name
     *
     * @param  string  $commandName  The name of the command
     * @return InfluxDBHttpCommand The command object
     *
     * @throws ConnectionException If the command is unknown
     */
    public static function create(string $commandName): InfluxDBHttpCommand
    {
        return match ($commandName) {
            'query' => new QueryCommand,
            'write' => new WriteCommand,
            'health' => new HealthCommand,
            'ping' => new PingCommand,
            'get_buckets' => new GetBucketsCommand,
            'create_bucket' => new CreateBucketCommand,
            'delete_measurement' => new DeleteMeasurementCommand,
            default => throw new ConnectionException("Unknown command: $commandName")
        };
    }
}

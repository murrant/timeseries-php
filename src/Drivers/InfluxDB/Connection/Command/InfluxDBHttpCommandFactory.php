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
     * @param  int  $apiVersion  The InfluxDB API version (1 or 2)
     * @return InfluxDBHttpCommand The command object
     *
     * @throws ConnectionException If the command is unknown or the API version is unsupported
     */
    public static function create(string $commandName, int $apiVersion = 2): InfluxDBHttpCommand
    {
        if ($apiVersion !== 1 && $apiVersion !== 2) {
            throw new ConnectionException("Unsupported API version: $apiVersion. Only versions 1 and 2 are supported.");
        }

        if ($apiVersion === 1) {
            return match ($commandName) {
                'query' => new V1\QueryCommand,
                'write' => new V1\WriteCommand,
                'health' => new V1\HealthCommand,
                'ping' => new V1\PingCommand,
                'get_buckets' => new V1\GetBucketsCommand,
                'create_bucket' => new V1\CreateBucketCommand,
                'delete_measurement' => new V1\DeleteMeasurementCommand,
                default => throw new ConnectionException("Unknown command: $commandName")
            };
        }

        // Default to API v2
        return match ($commandName) {
            'query' => new V2\QueryCommand,
            'write' => new V2\WriteCommand,
            'health' => new V2\HealthCommand,
            'ping' => new V2\PingCommand,
            'get_buckets' => new V2\GetBucketsCommand,
            'create_bucket' => new V2\CreateBucketCommand,
            'delete_measurement' => new V2\DeleteMeasurementCommand,
            default => throw new ConnectionException("Unknown command: $commandName")
        };
    }
}

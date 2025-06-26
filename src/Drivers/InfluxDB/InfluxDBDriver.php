<?php

namespace TimeSeriesPhp\Drivers\InfluxDB;

use DateTime;
use Psr\Log\LoggerInterface;
use TimeSeriesPhp\Contracts\Connection\ConnectionAdapterInterface;
use TimeSeriesPhp\Contracts\Driver\ConfigurableInterface;
use TimeSeriesPhp\Contracts\Query\RawQueryInterface;
use TimeSeriesPhp\Core\Attributes\Driver;
use TimeSeriesPhp\Core\Data\DataPoint;
use TimeSeriesPhp\Core\Data\QueryResult;
use TimeSeriesPhp\Core\Driver\AbstractTimeSeriesDB;
use TimeSeriesPhp\Core\Driver\Formatter\LineProtocolFormatter;
use TimeSeriesPhp\Drivers\InfluxDB\Connection\HttpConnectionAdapter;
use TimeSeriesPhp\Drivers\InfluxDB\Connection\SocketConnectionAdapter;
use TimeSeriesPhp\Exceptions\Driver\ConnectionException;
use TimeSeriesPhp\Exceptions\Driver\DatabaseException;
use TimeSeriesPhp\Exceptions\Driver\WriteException;
use TimeSeriesPhp\Exceptions\Query\RawQueryException;

#[Driver(name: 'influxdb', queryBuilderClass: InfluxDBQueryBuilder::class, configClass: InfluxDBConfig::class)]
class InfluxDBDriver extends AbstractTimeSeriesDB implements ConfigurableInterface
{
    protected InfluxDBConfig $config;

    protected InfluxDBQueryBuilder $influxQueryBuilder;

    protected ConnectionAdapterInterface $connectionAdapter;

    public function __construct(
        InfluxDBQueryBuilder $queryBuilder,
        LoggerInterface $logger,
        InfluxDBConfig $config,
        ?ConnectionAdapterInterface $connectionAdapter,
        protected LineProtocolFormatter $writeFormatter = new LineProtocolFormatter,
    ) {
        parent::__construct($queryBuilder, $logger);

        $this->config = $config;
        $this->influxQueryBuilder = $queryBuilder;

        $this->connectionAdapter = $connectionAdapter ?? match ($this->config->connection_type) {
            'socket' => new SocketConnectionAdapter($this->config, $this->logger),
            default => $this->createHttpConnectionAdapter(),
        };
    }

    /**
     * Configure the driver with the given configuration
     *
     * @param  array<string, mixed>  $config
     */
    public function configure(array $config): void
    {
        $this->config = $this->config->createFromArray($config);

        // Recreate the connection adapter with the updated config
        if ($this->config->connection_type === 'http') {
            $this->connectionAdapter = $this->createHttpConnectionAdapter();
        } else {
            $this->connectionAdapter = new SocketConnectionAdapter($this->config, $this->logger);
        }
    }

    protected function doConnect(): bool
    {
        try {
            // Set up the query builder
            $this->influxQueryBuilder->bucket = $this->config->bucket;

            return $this->connectionAdapter->connect();
        } catch (\Throwable $e) {
            $this->logger->error('InfluxDB connection failed: '.$e->getMessage(), [
                'exception' => $e::class,
                'connection_type' => $this->config->connection_type,
            ]);

            throw new ConnectionException('Failed to connect to InfluxDB: '.$e->getMessage(), 0, $e);
        }
    }

    protected function doWrite(DataPoint $dataPoint): bool
    {
        if (! $this->isConnected()) {
            throw new WriteException('Not connected to InfluxDB');
        }

        try {
            $line = $this->writeFormatter->format($dataPoint);

            $response = $this->connectionAdapter->executeCommand('write', $line);

            if (! $response->success) {
                throw new WriteException('Failed to write data point: '.$response->error);
            }

            return true;
        } catch (\Throwable $e) {
            throw new WriteException('Failed to write data point: '.$e->getMessage(), 0, $e);
        }
    }

    protected function doWriteBatch(array $dataPoints): bool
    {
        if (! $this->isConnected()) {
            throw new WriteException('Not connected to InfluxDB');
        }

        if (empty($dataPoints)) {
            return true;
        }

        try {
            $lines = '';
            foreach ($dataPoints as $dataPoint) {
                $lines .= $this->writeFormatter->format($dataPoint)."\n";
            }
            $response = $this->connectionAdapter->executeCommand('write', $lines);

            if (! $response->success) {
                throw new WriteException('Failed to write batch data: '.$response->error);
            }

            return true;
        } catch (\Throwable $e) {
            throw new WriteException('Failed to write batch data: '.$e->getMessage(), 0, $e);
        }
    }

    public function rawQuery(RawQueryInterface $query): QueryResult
    {
        if (! $this->isConnected()) {
            throw new RawQueryException($query, 'Not connected to InfluxDB');
        }

        try {
            // Execute the query command
            $response = $this->connectionAdapter->executeCommand('write', $query->getRawQuery());

            if (! $response->success) {
                throw new RawQueryException($query, 'Query execution failed: '.$response->error);
            }

            // Parse the response into a QueryResult
            return $this->parseQueryResponse($response->data);
        } catch (\Throwable $e) {
            throw new RawQueryException($query, 'Query execution failed: '.$e->getMessage(), 0, $e);
        }
    }

    private function parseQueryResponse(string $responseData): QueryResult
    {
        $result = new QueryResult;

        // TODO implement

        return $result;
    }

    public function createDatabase(string $database): bool
    {
        if (! $this->isConnected()) {
            throw new ConnectionException('Not connected to InfluxDB');
        }

        try {
            // Create the bucket request payload
            $payload = json_encode([
                'name' => $database,
                'retentionRules' => [
                    [
                        'everySeconds' => 0, // No expiration
                        'type' => 'expire',
                    ],
                ],
            ]);

            if ($payload === false) {
                throw new \JsonException('Failed to encode bucket creation request');
            }

            $response = $this->connectionAdapter->executeCommand('create_bucket', $payload);

            if (! $response->success) {
                throw new DatabaseException('Failed to create bucket: '.$response->error);
            }

            return true;
        } catch (\Throwable $e) {
            $this->logger->error('Failed to create bucket: '.$e->getMessage(), [
                'exception' => $e::class,
                'database' => $database,
            ]);

            throw new DatabaseException('Failed to create database: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * @return string[]
     */
    public function getDatabases(): array
    {
        if (! $this->isConnected()) {
            throw new ConnectionException('Not connected to InfluxDB');
        }

        try {
            $response = $this->connectionAdapter->executeCommand('get_buckets', '');

            if (! $response->success) {
                throw new DatabaseException('Failed to list buckets: '.$response->error);
            }

            $data = json_decode($response->data, true);
            if (! is_array($data) || ! isset($data['buckets']) || ! is_array($data['buckets'])) {
                throw new DatabaseException('Invalid response format from buckets API');
            }

            $bucketNames = [];
            foreach ($data['buckets'] as $bucket) {
                if (is_array($bucket) && isset($bucket['name']) && is_string($bucket['name'])) {
                    $bucketNames[] = $bucket['name'];
                }
            }

            return $bucketNames;
        } catch (\Throwable $e) {
            $this->logger->error('Failed to list buckets: '.$e->getMessage(), [
                'exception' => $e::class,
            ]);

            throw new DatabaseException('Failed to list databases: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * @throws ConnectionException
     */
    public function deleteMeasurement(string $measurement, ?DateTime $start = null, ?DateTime $stop = null): bool
    {
        if (! $this->isConnected()) {
            throw new ConnectionException('Not connected to InfluxDB');
        }

        try {
            // Create the delete request payload
            $startTime = $start ?? new DateTime('1970-01-01T00:00:00Z');
            $stopTime = $stop ?? new DateTime;

            $payload = json_encode([
                'start' => $startTime->format('Y-m-d\TH:i:s\Z'),
                'stop' => $stopTime->format('Y-m-d\TH:i:s\Z'),
                'predicate' => "_measurement=\"{$measurement}\"",
            ]);

            if ($payload === false) {
                throw new \JsonException('Failed to encode delete request');
            }

            $response = $this->connectionAdapter->executeCommand('delete_measurement', $payload);

            if (! $response->success) {
                throw new DatabaseException('Failed to delete measurement: '.$response->error);
            }

            return true;
        } catch (\Throwable $e) {
            $this->logger->error('Failed to delete measurement: '.$e->getMessage(), [
                'exception' => $e::class,
                'measurement' => $measurement,
                'start' => $start ? $start->format('c') : null,
                'stop' => $stop ? $stop->format('c') : null,
            ]);

            throw new DatabaseException('Failed to delete measurement: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * @return array{'status': 'success'|'fail', 'build': string, 'version': string}
     */
    public function getHealth(): array
    {
        try {
            if (! $this->isConnected()) {
                throw new ConnectionException('Not connected to InfluxDB');
            }

            $response = $this->connectionAdapter->executeCommand('ping', '');

            if (! $response->success) {
                throw new ConnectionException('Failed to ping InfluxDB: '.$response->error);
            }

            // Extract build and version from response metadata
            $build = 'Unknown';
            $version = 'Unknown';

            if (isset($response->metadata['headers']) && is_array($response->metadata['headers'])) {
                foreach ($response->metadata['headers'] as $header) {
                    if (is_string($header) && str_starts_with($header, 'X-Influxdb-Build:')) {
                        $build = trim(substr($header, strlen('X-Influxdb-Build:')));
                    } elseif (is_string($header) && str_starts_with($header, 'X-Influxdb-Version:')) {
                        $version = trim(substr($header, strlen('X-Influxdb-Version:')));
                    }
                }
            }

            return [
                'status' => 'success',
                'build' => $build,
                'version' => $version,
            ];
        } catch (\Throwable $e) {
            return [
                'status' => 'fail',
                'build' => $e->getMessage(),
                'version' => 'Unknown',
            ];
        }
    }

    /**
     * Create a new HttpConnectionAdapter with PSR HTTP client dependencies
     */
    private function createHttpConnectionAdapter(): HttpConnectionAdapter
    {
        // Use PHP-HTTP discovery to find implementations
        $httpClient = \Http\Discovery\Psr18ClientDiscovery::find();
        $requestFactory = \Http\Discovery\Psr17FactoryDiscovery::findRequestFactory();
        $streamFactory = \Http\Discovery\Psr17FactoryDiscovery::findStreamFactory();

        return new HttpConnectionAdapter(
            $this->config,
            $this->logger,
            $httpClient,
            $requestFactory,
            $streamFactory
        );
    }

    public function close(): void
    {
        $this->connectionAdapter->close();
    }

    public function isConnected(): bool
    {
        return $this->connectionAdapter->isConnected();
    }

    public function __destruct()
    {
        $this->close();
    }
}

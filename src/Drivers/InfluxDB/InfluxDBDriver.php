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
use TimeSeriesPhp\Drivers\InfluxDB\Connection\UdpConnectionAdapter;
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

        $this->connectionAdapter = $connectionAdapter ?? $this->createConnectionAdapter();
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
        $this->connectionAdapter = $this->createConnectionAdapter();
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
            $response = $this->connectionAdapter->executeCommand('query', $query->getRawQuery());

            if (! $response->success) {
                throw new RawQueryException($query, 'Query execution failed: '.$response->error);
            }

            // Parse the response into a QueryResult
            return $this->parseQueryResponse($response->data);
        } catch (\Throwable $e) {
            throw new RawQueryException($query, 'Query execution failed: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * Parse the query response from InfluxDB
     *
     * @param  string  $responseData  The response data from InfluxDB
     * @return QueryResult The parsed query result
     */
    private function parseQueryResponse(string $responseData): QueryResult
    {
        $result = new QueryResult;

        $this->logger->debug('Raw response data from InfluxDB: ' . $responseData);

        try {
            $data = json_decode($responseData, true);

            if (! $data || ! is_array($data)) {
                $this->logger->debug('No data or invalid data in response');
                return $result;
            }

            // Handle InfluxDB v2 Flux response format
            if (isset($data['results']) && is_array($data['results'])) {
                // InfluxDB v1 format
                foreach ($data['results'] as $resultSet) {
                    if (! is_array($resultSet)) {
                        continue;
                    }

                    if (isset($resultSet['series']) && is_array($resultSet['series'])) {
                        foreach ($resultSet['series'] as $series) {
                            if (! is_array($series)) {
                                continue;
                            }

                            $name = isset($series['name']) && is_string($series['name']) ? $series['name'] : 'unknown';

                            // Ensure columns are strings
                            $rawColumns = isset($series['columns']) && is_array($series['columns']) ? $series['columns'] : [];
                            $columns = [];
                            foreach ($rawColumns as $col) {
                                $columns[] = is_string($col) ? $col : (is_scalar($col) ? (string) $col : 'unknown');
                            }

                            // Ensure values are properly formatted
                            $rawValues = isset($series['values']) && is_array($series['values']) ? $series['values'] : [];
                            $values = [];
                            foreach ($rawValues as $row) {
                                if (is_array($row)) {
                                    $values[] = $row;
                                }
                            }

                            // Ensure tags are properly formatted
                            $rawTags = isset($series['tags']) && is_array($series['tags']) ? $series['tags'] : [];
                            $tags = [];
                            foreach ($rawTags as $key => $value) {
                                if (is_string($key)) {
                                    $tags[$key] = $value;
                                }
                            }

                            $result->addSeries($name, $columns, $values, $tags);
                        }
                    }
                }
            } elseif (isset($data['tables']) && is_array($data['tables'])) {
                // InfluxDB v2 format with tables
                foreach ($data['tables'] as $table) {
                    if (! is_array($table)) {
                        continue;
                    }

                    if (isset($table['data']) && is_array($table['data'])) {
                        $columns = [];
                        $values = [];
                        $name = 'unknown';
                        $tags = [];

                        foreach ($table['data'] as $row) {
                            if (! is_array($row)) {
                                continue;
                            }

                            if (empty($columns) && isset($row['columns']) && is_array($row['columns'])) {
                                $columns = $row['columns'];
                            }

                            if (isset($row['values']) && is_array($row['values'])) {
                                $values[] = $row['values'];
                            }

                            // Try to extract measurement name
                            if ($name === 'unknown' && isset($row['_measurement']) && is_string($row['_measurement'])) {
                                $name = $row['_measurement'];
                            }

                            // Extract tags
                            foreach ($row as $key => $value) {
                                if (is_string($key) && strpos($key, '_') !== 0 && $key !== 'result' && $key !== 'table') {
                                    $tags[$key] = $value;
                                }
                            }
                        }

                        // Ensure columns are strings
                        $stringColumns = [];
                        foreach ($columns as $col) {
                            $stringColumns[] = is_string($col) ? $col : (string) $col;
                        }

                        // Ensure values are properly formatted
                        $formattedValues = [];
                        foreach ($values as $row) {
                            if (is_array($row)) {
                                $formattedValues[] = $row;
                            }
                        }

                        // Ensure tags are properly formatted
                        $formattedTags = [];
                        foreach ($tags as $key => $value) {
                            if (is_string($key)) {
                                $formattedTags[$key] = $value;
                            }
                        }

                        $result->addSeries($name, $stringColumns, $formattedValues, $formattedTags);
                    }
                }
            } else {
                // Try to handle Flux CSV-like format
                // This is a simplified parser for the most common case
                $name = 'unknown';
                $columns = [];
                $values = [];
                $tags = [];

                // Extract data from the response
                if (is_array($data)) {
                    foreach ($data as $record) {
                        if (! is_array($record)) {
                            continue;
                        }

                        if (empty($columns)) {
                            // First record contains column names
                            $columns = array_keys($record);
                        }

                        // Extract measurement name if available
                        if (isset($record['_measurement']) && is_string($record['_measurement'])) {
                            $name = $record['_measurement'];
                        }

                        // Extract values
                        $rowValues = [];
                        foreach ($columns as $column) {
                            $rowValues[] = $record[$column] ?? null;
                        }
                        $values[] = $rowValues;

                        // Extract tags
                        foreach ($record as $key => $value) {
                            if (is_string($key) && strpos($key, '_') !== 0 && $key !== 'result' && $key !== 'table') {
                                $tags[$key] = $value;
                            }
                        }
                    }

                    if (! empty($columns) && ! empty($values)) {
                        // Ensure columns are strings
                        $stringColumns = [];
                        foreach ($columns as $col) {
                            $stringColumns[] = is_string($col) ? $col : (string) $col;
                        }

                        // Ensure values are properly formatted
                        $formattedValues = [];
                        foreach ($values as $row) {
                            if (is_array($row)) {
                                $formattedValues[] = $row;
                            }
                        }

                        // Ensure tags are properly formatted
                        $formattedTags = [];
                        foreach ($tags as $key => $value) {
                            if (is_string($key)) {
                                $formattedTags[$key] = $value;
                            }
                        }

                        $result->addSeries($name, $stringColumns, $formattedValues, $formattedTags);
                    }
                }
            }
        } catch (\Throwable $e) {
            // Log the error but return an empty result
            $this->logger->error('Failed to parse query response: '.$e->getMessage(), [
                'exception' => $e::class,
                'response' => $responseData,
            ]);
        }

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

    private function createConnectionAdapter(): ConnectionAdapterInterface
    {
        // Use PHP-HTTP discovery to find implementations
        $httpClient = \Http\Discovery\Psr18ClientDiscovery::find();
        $requestFactory = \Http\Discovery\Psr17FactoryDiscovery::findRequestFactory();
        $streamFactory = \Http\Discovery\Psr17FactoryDiscovery::findStreamFactory();

        if ($this->config->connection_type === 'udp') {
            return new UdpConnectionAdapter(
                $this->config,
                $this->logger,
                $httpClient,
                $requestFactory,
                $streamFactory
            );
        }

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

<?php

namespace TimeSeriesPhp\Drivers\Graphite;

use Exception;
use TimeSeriesPhp\Core\AbstractTimeSeriesDB;
use TimeSeriesPhp\Core\DataPoint;
use TimeSeriesPhp\Core\QueryResult;
use TimeSeriesPhp\Core\RawQueryContract;
use TimeSeriesPhp\Exceptions\ConfigurationException;
use TimeSeriesPhp\Exceptions\ConnectionException;
use TimeSeriesPhp\Exceptions\QueryException;
use TimeSeriesPhp\Exceptions\WriteException;

class GraphiteDriver extends AbstractTimeSeriesDB
{
    /**
     * @var resource|null
     */
    protected mixed $socket = null;

    protected string $host = 'localhost';

    protected int $port = 2003;

    protected string $protocol = 'tcp';

    protected int $timeout = 30;

    protected string $prefix = '';

    protected int $batchSize = 500;

    protected string $webUrl = '';

    /**
     * @throws ConfigurationException
     */
    protected function doConnect(): bool
    {
        if (! $this->config instanceof GraphiteConfig) {
            throw new ConfigurationException('Invalid configuration type. Expected GraphiteConfig.');
        }

        try {
            $this->host = $this->config->getString('host');
            $this->port = $this->config->getInt('port');
            $this->protocol = $this->config->getString('protocol');
            $this->timeout = $this->config->getInt('timeout');
            $this->prefix = $this->config->getString('prefix');
            $this->batchSize = $this->config->getInt('batch_size');
            $this->webUrl = $this->config->getWebUrl();

            // Initialize query builder
            $this->queryBuilder = new GraphiteQueryBuilder($this->prefix);

            // Test connection by opening and closing a socket
            $this->getSocket();
            $this->closeSocket();

            $this->connected = true;

            return true;
        } catch (Exception $e) {
            error_log('Graphite connection failed: '.$e->getMessage());
            $this->connected = false;

            return false;
        }
    }

    /**
     * @throws ConnectionException
     * @throws WriteException
     */
    public function write(DataPoint $dataPoint): bool
    {
        if (! $this->isConnected()) {
            throw new ConnectionException('Not connected to Graphite');
        }

        try {
            $timestamp = $dataPoint->getTimestamp()->getTimestamp();
            $measurement = $dataPoint->getMeasurement();
            $fields = $dataPoint->getFields();
            $tagPath = $this->formatTagPath($dataPoint->getTags());

            // In Graphite, tags are typically part of the metric path
            $metricPrefix = $this->prefix ? $this->prefix.'.' : '';

            $success = true;

            // Write each field as a separate metric
            foreach ($fields as $key => $value) {
                $success &= $this->writeMetric($value, $metricPrefix, $measurement, $tagPath, $key, $timestamp);
            }

            $this->closeSocket();

            return (bool) $success;
        } catch (Exception $e) {
            error_log('Graphite write failed: '.$e->getMessage());
            $this->closeSocket();
            throw new WriteException('Failed to write data to Graphite: '.$e->getMessage());
        }
    }

    /**
     * @param  DataPoint[]  $dataPoints
     *
     * @throws ConnectionException
     * @throws WriteException
     */
    public function writeBatch(array $dataPoints): bool
    {
        if (! $this->isConnected()) {
            throw new ConnectionException('Not connected to Graphite');
        }

        try {
            $this->getSocket();

            $success = true;
            $batchCount = 0;
            $metricPrefix = $this->prefix ? $this->prefix.'.' : '';

            foreach ($dataPoints as $dataPoint) {
                $timestamp = $dataPoint->getTimestamp()->getTimestamp();
                $measurement = $dataPoint->getMeasurement();
                $fields = $dataPoint->getFields();
                $tagPath = $this->formatTagPath($dataPoint->getTags());

                // Write each field as a separate metric
                foreach ($fields as $key => $value) {
                    $success &= $this->writeMetric($value, $metricPrefix, $measurement, $tagPath, $key, $timestamp);
                    $batchCount++;

                    // If we've reached the batch size, flush the socket
                    if ($batchCount >= $this->batchSize) {
                        $this->closeSocket();
                        $batchCount = 0;
                    }
                }
            }

            $this->closeSocket();

            return (bool) $success;
        } catch (Exception $e) {
            error_log('Graphite batch write failed: '.$e->getMessage());
            $this->closeSocket();
            throw new WriteException('Failed to write batch data to Graphite: '.$e->getMessage());
        }
    }

    /**
     * @param  array<string, string>  $tags
     */
    private function formatTagPath(array $tags): string
    {
        $tagPath = '';
        if (! empty($tags)) {
            foreach ($tags as $key => $value) {
                $tagPath .= '.'.$key.'.'.$value;
            }
        }

        return $tagPath;
    }

    /**
     * @throws ConnectionException
     */
    private function writeMetric(mixed $value, string $metricPrefix, string $measurement, string $tagPath, string $key, int|string $timestamp): bool
    {
        if (! is_numeric($value)) {
            // Graphite only supports numeric values
            return false;
        }

        $metricPath = $metricPrefix.$measurement.$tagPath.'.'.$key;
        $line = $metricPath.' '.$value.' '.$timestamp."\n";

        return fwrite($this->getSocket(), $line) !== false;
    }

    /**
     * @throws QueryException
     */
    public function rawQuery(RawQueryContract $query): QueryResult
    {
        if (! $this->isConnected()) {
            throw new QueryException($query, 'Not connected to Graphite');
        }

        try {
            $queryString = $query->getRawQuery();
            $url = $this->webUrl.'?'.$queryString;

            $context = stream_context_create([
                'http' => [
                    'timeout' => $this->timeout,
                ],
            ]);

            $response = file_get_contents($url, false, $context);

            if ($response === false) {
                throw new Exception('Failed to get response from Graphite');
            }

            /** @var array<array{'target': string, 'datapoints': array<array{string, int}>}> $data */
            $data = json_decode($response, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Failed to parse JSON response: '.json_last_error_msg());
            }

            // Transform Graphite response to a standard format
            $result = new QueryResult;

            foreach ($data as $series) {
                $target = $series['target'];

                foreach ($series['datapoints'] as $point) {
                    [$value, $timestamp] = $point;

                    $result->appendPoint($timestamp, $target, $value);
                }
            }

            return $result;
        } catch (Exception $e) {
            throw new QueryException($query, 'Query execution failed: '.$e->getMessage());
        }
    }

    public function createDatabase(string $database): bool
    {
        // Graphite doesn't have a concept of databases that need to be created
        // Metrics are automatically created when data is written
        return true;
    }

    /**
     * @return string[]
     */
    public function getDatabases(): array
    {
        // Graphite doesn't have a concept of databases
        // We could potentially return a list of top-level metrics, but that's not
        // directly equivalent to databases in other systems
        return ['default'];
    }

    public function close(): void
    {
        $this->closeSocket();
        $this->connected = false;
    }

    /**
     * @return resource
     *
     * @throws ConnectionException
     */
    protected function getSocket(): mixed
    {
        if ($this->socket !== null) {
            return $this->socket;
        }

        $errno = 0;
        $errstr = '';

        $protocol = $this->protocol === 'udp' ? 'udp' : 'tcp';
        $this->socket = fsockopen($protocol.'://'.$this->host, $this->port, $errno, $errstr, $this->timeout) ?: null;

        if ($this->socket === null) {
            throw new ConnectionException("Failed to connect to Graphite: {$errstr} ({$errno})");
        }

        // Set socket timeout
        stream_set_timeout($this->socket, $this->timeout);

        return $this->socket;
    }

    protected function closeSocket(): void
    {
        if ($this->socket) {
            fclose($this->socket);
            $this->socket = null;
        }
    }

    public function isConnected(): bool
    {
        return $this->connected;
    }

    public function __destruct()
    {
        $this->close();
    }
}

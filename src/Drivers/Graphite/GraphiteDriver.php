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
    private mixed $socket = null;

    private string $host = 'localhost';

    private int $port = 2003;

    private string $protocol = 'tcp';

    private int $timeout = 30;

    private string $prefix = '';

    private int $batchSize = 500;

    private string $webUrl = '';

    protected function doConnect(): bool
    {
        if (!$this->config instanceof GraphiteConfig) {
            throw new ConfigurationException('Invalid configuration type. Expected GraphiteConfig.');
        }

        try {
            $this->host = $this->config->get('host');
            $this->port = $this->config->get('port');
            $this->protocol = $this->config->get('protocol');
            $this->timeout = $this->config->get('timeout');
            $this->prefix = $this->config->get('prefix');
            $this->batchSize = $this->config->get('batch_size');
            $this->webUrl = $this->config->getWebUrl();

            // Initialize query builder
            $this->queryBuilder = new GraphiteQueryBuilder($this->prefix);

            // Test connection by opening and closing a socket
            $this->openSocket();
            $this->closeSocket();

            $this->connected = true;
            return true;
        } catch (Exception $e) {
            error_log('Graphite connection failed: ' . $e->getMessage());
            $this->connected = false;
            return false;
        }
    }

    /**
     * @throws WriteException
     */
    public function write(DataPoint $dataPoint): bool
    {
        if (!$this->isConnected()) {
            throw new ConnectionException('Not connected to Graphite');
        }

        try {
            $this->openSocket();

            $timestamp = $dataPoint->getTimestamp()->getTimestamp();
            $measurement = $dataPoint->getMeasurement();
            $fields = $dataPoint->getFields();
            $tags = $dataPoint->getTags();

            // In Graphite, tags are typically part of the metric path
            $metricPrefix = $this->prefix ? $this->prefix . '.' : '';

            // Add tags to the metric path if present
            $tagPath = '';
            if (!empty($tags)) {
                foreach ($tags as $key => $value) {
                    $tagPath .= '.' . $key . '.' . $value;
                }
            }

            $success = true;

            // Write each field as a separate metric
            foreach ($fields as $key => $value) {
                if (!is_numeric($value)) {
                    // Graphite only supports numeric values
                    continue;
                }

                $metricPath = $metricPrefix . $measurement . $tagPath . '.' . $key;
                $line = $metricPath . ' ' . $value . ' ' . $timestamp . "\n";

                $result = fwrite($this->socket, $line);
                if ($result === false) {
                    $success = false;
                }
            }

            $this->closeSocket();

            return $success;
        } catch (Exception $e) {
            error_log('Graphite write failed: ' . $e->getMessage());
            $this->closeSocket();
            throw new WriteException('Failed to write data to Graphite: ' . $e->getMessage());
        }
    }

    /**
     * @param DataPoint[] $dataPoints
     * @throws WriteException
     */
    public function writeBatch(array $dataPoints): bool
    {
        if (!$this->isConnected()) {
            throw new ConnectionException('Not connected to Graphite');
        }

        try {
            $this->openSocket();

            $success = true;
            $batchCount = 0;
            $metricPrefix = $this->prefix ? $this->prefix . '.' : '';

            foreach ($dataPoints as $dataPoint) {
                $timestamp = $dataPoint->getTimestamp()->getTimestamp();
                $measurement = $dataPoint->getMeasurement();
                $fields = $dataPoint->getFields();
                $tags = $dataPoint->getTags();

                // Add tags to the metric path if present
                $tagPath = '';
                if (!empty($tags)) {
                    foreach ($tags as $key => $value) {
                        $tagPath .= '.' . $key . '.' . $value;
                    }
                }

                // Write each field as a separate metric
                foreach ($fields as $key => $value) {
                    if (!is_numeric($value)) {
                        // Graphite only supports numeric values
                        continue;
                    }

                    $metricPath = $metricPrefix . $measurement . $tagPath . '.' . $key;
                    $line = $metricPath . ' ' . $value . ' ' . $timestamp . "\n";

                    $result = fwrite($this->socket, $line);
                    if ($result === false) {
                        $success = false;
                    }

                    $batchCount++;

                    // If we've reached the batch size, flush and reopen the socket
                    if ($batchCount >= $this->batchSize) {
                        $this->closeSocket();
                        $this->openSocket();
                        $batchCount = 0;
                    }
                }
            }

            $this->closeSocket();

            return $success;
        } catch (Exception $e) {
            error_log('Graphite batch write failed: ' . $e->getMessage());
            $this->closeSocket();
            throw new WriteException('Failed to write batch data to Graphite: ' . $e->getMessage());
        }
    }

    /**
     * @throws QueryException
     */
    public function rawQuery(RawQueryContract $query): QueryResult
    {
        if (!$this->isConnected()) {
            throw new QueryException($query, 'Not connected to Graphite');
        }

        try {
            $queryString = $query->getRawQuery();
            $url = $this->webUrl . '?' . $queryString;

            $context = stream_context_create([
                'http' => [
                    'timeout' => $this->timeout,
                ],
            ]);

            $response = file_get_contents($url, false, $context);

            if ($response === false) {
                throw new Exception('Failed to get response from Graphite');
            }

            $data = json_decode($response, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Failed to parse JSON response: ' . json_last_error_msg());
            }

            // Transform Graphite response to a standard format
            $result = [];

            foreach ($data as $series) {
                $target = $series['target'];
                $datapoints = $series['datapoints'];

                foreach ($datapoints as $point) {
                    [$value, $timestamp] = $point;

                    // Skip null values
                    if ($value === null) {
                        continue;
                    }

                    $result[] = [
                        'target' => $target,
                        'timestamp' => $timestamp,
                        'value' => $value,
                    ];
                }
            }

            return new QueryResult($result);
        } catch (Exception $e) {
            throw new QueryException($query, 'Query execution failed: ' . $e->getMessage());
        }
    }

    public function createDatabase(string $database): bool
    {
        // Graphite doesn't have a concept of databases that need to be created
        // Metrics are automatically created when data is written
        return true;
    }

    public function listDatabases(): array
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

    private function openSocket(): void
    {
        if ($this->socket !== null) {
            return;
        }

        $errno = 0;
        $errstr = '';

        $protocol = $this->protocol === 'udp' ? 'udp' : 'tcp';
        $this->socket = fsockopen($protocol . '://' . $this->host, $this->port, $errno, $errstr, $this->timeout);

        if (!$this->socket) {
            throw new ConnectionException("Failed to connect to Graphite: {$errstr} ({$errno})");
        }

        // Set socket timeout
        stream_set_timeout($this->socket, $this->timeout);
    }

    private function closeSocket(): void
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

<?php

namespace TimeSeriesPhp\Drivers\Graphite;

use Psr\Log\LoggerInterface;
use TimeSeriesPhp\Contracts\Driver\ConfigurableInterface;
use TimeSeriesPhp\Contracts\Query\RawQueryInterface;
use TimeSeriesPhp\Core\Attributes\Driver;
use TimeSeriesPhp\Core\Data\DataPoint;
use TimeSeriesPhp\Core\Data\QueryResult;
use TimeSeriesPhp\Core\Driver\AbstractTimeSeriesDB;
use TimeSeriesPhp\Drivers\Graphite\Query\GraphiteQueryBuilder;
use TimeSeriesPhp\Exceptions\Driver\ConnectionException;
use TimeSeriesPhp\Exceptions\Driver\WriteException;
use TimeSeriesPhp\Exceptions\Query\RawQueryException;

#[Driver(name: 'graphite', queryBuilderClass: GraphiteQueryBuilder::class, configClass: GraphiteConfig::class)]
class GraphiteDriver extends AbstractTimeSeriesDB implements ConfigurableInterface
{
    /** @var resource|null */
    private $socket = null;

    public function __construct(
        protected GraphiteConfig $config,
        GraphiteQueryBuilder $queryBuilder,
        LoggerInterface $logger,
    ) {
        parent::__construct($queryBuilder, $logger);
    }

    /**
     * Configure the driver with the given configuration
     *
     * @param  array<string, mixed>  $config
     */
    public function configure(array $config): void
    {
        $this->config = $this->config->createFromArray($config);
    }

    /**
     * @var bool Whether the driver is connected
     */
    protected bool $connected = false;

    protected function doConnect(): bool
    {
        try {
            // Test connection by opening and closing a socket
            $this->getSocket();
            $this->closeSocket();

            $this->connected = true;

            $this->logger->info('Connected to Graphite successfully', [
                'host' => $this->config->host,
                'port' => $this->config->port,
                'protocol' => $this->config->protocol,
                'prefix' => $this->config->prefix,
                'batch_size' => $this->config->batch_size,
            ]);

            return true;
        } catch (\Exception $e) {
            $this->logger->error('Graphite connection failed: '.$e->getMessage(), [
                'exception' => $e::class,
                'host' => $this->config->host,
                'port' => $this->config->port,
                'protocol' => $this->config->protocol,
            ]);
            $this->connected = false;

            return false;
        }
    }

    /**
     * @throws ConnectionException
     * @throws WriteException
     */
    protected function doWrite(DataPoint $dataPoint): bool
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
            $metricPrefix = $this->config->prefix ? $this->config->prefix.'.' : '';

            $success = true;

            // Write each field as a separate metric
            foreach ($fields as $key => $value) {
                $success &= $this->writeMetric($value, $metricPrefix, $measurement, $tagPath, $key, $timestamp);
            }

            $this->closeSocket();

            return (bool) $success;
        } catch (\Exception $e) {
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
    protected function doWriteBatch(array $dataPoints): bool
    {
        if (! $this->isConnected()) {
            throw new ConnectionException('Not connected to Graphite');
        }

        try {
            $this->getSocket();

            $success = true;
            $batchCount = 0;
            $metricPrefix = $this->config->prefix ? $this->config->prefix.'.' : '';

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
                    if ($batchCount >= $this->config->batch_size) {
                        $this->closeSocket();
                        $batchCount = 0;
                    }
                }
            }

            $this->closeSocket();

            return (bool) $success;
        } catch (\Exception $e) {
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

        // TODO wtf, this is the job of the Query Builder
        $metricPath = $metricPrefix.$measurement.$tagPath.'.'.$key;
        $line = $metricPath.' '.$value.' '.$timestamp."\n";

        return fwrite($this->getSocket(), $line) !== false;
    }

    /**
     * @throws RawQueryException|ConnectionException
     */
    public function rawQuery(RawQueryInterface $query): QueryResult
    {
        if (! $this->isConnected()) {
            throw new ConnectionException('Not connected to Graphite');
        }

        try {
            $queryString = $query->getRawQuery();
            $url = $this->config->getWebUrl().'?'.$queryString;

            $context = stream_context_create([
                'http' => [
                    'timeout' => $this->config->timeout,
                ],
            ]);

            $response = file_get_contents($url, false, $context);

            if ($response === false) {
                throw new ConnectionException('Failed to get response from Graphite');
            }

            // Check if the response is empty or not valid JSON
            if (empty($response) || $response[0] !== '[') {
                // Return an empty result if the response is not valid
                $this->logger->warning('Graphite returned an invalid or empty response', [
                    'url' => $url,
                    'response_length' => strlen($response),
                    'response_start' => substr($response, 0, 20),
                ]);

                return new QueryResult;
            }

            /** @var array<array{'target': string, 'datapoints': array<array{string, int}>}> $data */
            $data = json_decode($response, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->logger->warning('Failed to parse Graphite JSON response', [
                    'error' => json_last_error_msg(),
                    'response_length' => strlen($response),
                ]);

                return new QueryResult;
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
        } catch (\Exception $e) {
            throw new RawQueryException($query, 'Query execution failed: '.$e->getMessage());
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
        return [];
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

        $protocol = $this->config->protocol === 'udp' ? 'udp' : 'tcp';
        $this->socket = fsockopen($protocol.'://'.$this->config->host, $this->config->port, $errno, $errstr, $this->config->timeout) ?: null;

        if ($this->socket === null) {
            throw new ConnectionException("Failed to connect to Graphite: {$errstr} ({$errno})");
        }

        // Set socket timeout
        stream_set_timeout($this->socket, $this->config->timeout);

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

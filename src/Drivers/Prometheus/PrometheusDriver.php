<?php

namespace TimeSeriesPhp\Drivers\Prometheus;

use Exception;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UriFactoryInterface;
use TimeSeriesPhp\Contracts\Query\RawQueryInterface;
use TimeSeriesPhp\Core\Attributes\Driver;
use TimeSeriesPhp\Core\Data\DataPoint;
use TimeSeriesPhp\Core\Data\QueryResult;
use TimeSeriesPhp\Core\Driver\AbstractTimeSeriesDB;
use TimeSeriesPhp\Drivers\Prometheus\Config\PrometheusConfig;
use TimeSeriesPhp\Exceptions\Config\ConfigurationException;
use TimeSeriesPhp\Exceptions\Driver\ConnectionException;
use TimeSeriesPhp\Exceptions\Query\RawQueryException;
use TimeSeriesPhp\Exceptions\TSDBException;
use TimeSeriesPhp\Services\Logs\Logger;

#[Driver(name: 'prometheus', configClass: PrometheusConfig::class)]
class PrometheusDriver extends AbstractTimeSeriesDB
{
    private string $apiUrl = '';

    private int $timeout = 0;

    private bool $verifySSL = true;

    private bool $debug = false;

    /**
     * @var ClientInterface The HTTP client
     */
    private ClientInterface $client;

    /**
     * @var RequestFactoryInterface The request factory
     */
    private RequestFactoryInterface $requestFactory;

    /**
     * @var UriFactoryInterface The URI factory
     */
    private UriFactoryInterface $uriFactory;

    /**
     * @var StreamFactoryInterface The stream factory
     */
    private StreamFactoryInterface $streamFactory;

    /**
     * @var \TimeSeriesPhp\Drivers\Prometheus\Query\QueryBuilder The query builder
     */
    private \TimeSeriesPhp\Drivers\Prometheus\Query\QueryBuilder $prometheusQueryBuilder;

    /**
     * @var bool Whether the driver is connected
     */
    private bool $connected = false;

    /**
     * Constructor
     *
     * @param  ClientInterface  $client  The HTTP client
     * @param  RequestFactoryInterface  $requestFactory  The request factory
     * @param  UriFactoryInterface  $uriFactory  The URI factory
     * @param  StreamFactoryInterface  $streamFactory  The stream factory
     * @param  \TimeSeriesPhp\Drivers\Prometheus\Query\QueryBuilder  $prometheusQueryBuilder  The query builder
     * @param  \TimeSeriesPhp\Contracts\Query\QueryBuilderInterface|null  $parentQueryBuilderFactory  The parent query builder factory
     */
    public function __construct(
        ClientInterface $client,
        RequestFactoryInterface $requestFactory,
        UriFactoryInterface $uriFactory,
        StreamFactoryInterface $streamFactory,
        \TimeSeriesPhp\Drivers\Prometheus\Query\QueryBuilder $prometheusQueryBuilder,
        ?\TimeSeriesPhp\Contracts\Query\QueryBuilderInterface $parentQueryBuilderFactory = null
    ) {
        parent::__construct($parentQueryBuilderFactory);

        $this->client = $client;
        $this->requestFactory = $requestFactory;
        $this->uriFactory = $uriFactory;
        $this->streamFactory = $streamFactory;
        $this->prometheusQueryBuilder = $prometheusQueryBuilder;
    }

    protected function doConnect(): bool
    {
        if (! $this->config instanceof PrometheusConfig) {
            throw new ConfigurationException('Invalid configuration type. Expected PrometheusConfig.');
        }

        try {
            // Get configuration values directly
            $this->apiUrl = rtrim($this->config->getString('url'), '/');
            $this->timeout = $this->config->getInt('timeout');
            $this->verifySSL = $this->config->getBool('verify_ssl');
            $this->debug = $this->config->getBool('debug');

            // Initialize the query builder
            $this->queryBuilder = $this->prometheusQueryBuilder;

            // Test connection by pinging the API
            $response = $this->makeApiRequest('/api/v1/status/config');
            $this->connected = $response['status'] === 'success';

            if ($this->connected) {
                Logger::info('Connected to Prometheus successfully', [
                    'url' => $this->apiUrl,
                    'timeout' => $this->timeout,
                    'verify_ssl' => $this->verifySSL,
                    'debug' => $this->debug,
                ]);
            }

            return $this->connected;
        } catch (Exception $e) {
            Logger::error('Prometheus connection failed: '.$e->getMessage(), [
                'exception' => get_class($e),
                'url' => $this->apiUrl,
            ]);
            $this->connected = false;

            throw new ConnectionException('Failed to connect to Prometheus: '.$e->getMessage(), 0, $e);
        }
    }

    protected function doWrite(DataPoint $dataPoint): bool
    {
        // Prometheus typically receives metrics via scraping or push gateway
        // This method is a placeholder as direct writes are not typically done this way
        return true;
    }

    /**
     * @throws RawQueryException|ConnectionException
     */
    public function rawQuery(RawQueryInterface $query): QueryResult
    {
        if (! $this->connected) {
            throw new ConnectionException('Not connected to Prometheus');
        }

        try {
            $queryString = $query->getRawQuery();

            // Extract time range from comments if present
            $timeParams = [];
            $queryString = match (true) {
                preg_match('/#\s*time range:\s*([^\s]+)\s+to\s+([^\s]+)/', $queryString, $matches) => (function () use (&$timeParams, $queryString, $matches) {
                    $timeParams['start'] = $matches[1];
                    $timeParams['end'] = $matches[2];

                    // Remove the comment from the query
                    return preg_replace('/#\s*time range:[^\n]+/', '', $queryString) ?? $queryString;
                })(),
                preg_match('/#\s*relative time:\s*([^\n]+)/', $queryString, $matches) => (function () use (&$timeParams, $queryString, $matches) {
                    // Convert relative time to absolute time
                    $timeParams['time'] = $matches[1];

                    // Remove the comment from the query
                    return preg_replace('/#\s*relative time:[^\n]+/', '', $queryString) ?? $queryString;
                })(),
                default => $queryString
            };

            // Determine which API endpoint to use based on the query
            $params = ['query' => trim($queryString)];

            // Add time parameters and determine endpoint
            [$endpoint, $params] = match (true) {
                isset($timeParams['start']) => [
                    '/api/v1/query_range',
                    array_merge($params, [
                        'start' => $timeParams['start'],
                        'end' => $timeParams['end'],
                        'step' => '15s', // Default step
                    ]),
                ],
                isset($timeParams['time']) => [
                    '/api/v1/query',
                    array_merge($params, ['time' => $timeParams['time']]),
                ],
                default => ['/api/v1/query', $params]
            };

            // Execute the query
            $response = $this->makeApiRequest($endpoint, $params);

            if ($response['status'] !== 'success') {
                throw new RawQueryException($query, 'Query execution failed: '.($response['error'] ?? 'Unknown error'));
            }

            // Process the result
            $result = new QueryResult;
            foreach ($response['data']['result'] ?? [] as $item) {
                /** @var array{'metric': array{'__name__'?: string}, 'value'?: array{int, ?scalar}, 'values'?: array<array{int, ?scalar}>} $item */
                // Get the metric name or use a default
                $metricName = $item['metric']['__name__'] ?? 'value';

                // Handle single value result (instant query)
                if (isset($item['value'])) {
                    [$timestamp, $value] = $item['value'];
                    $result->appendPoint($timestamp, $metricName, $value);
                } elseif (isset($item['values'])) {
                    foreach ($item['values'] as $valueItem) {
                        [$timestamp, $value] = $valueItem;
                        $result->appendPoint($timestamp, $metricName, $value);
                    }
                }
            }

            return $result;
        } catch (Exception $e) {
            throw new RawQueryException($query, 'Query execution failed: '.$e->getMessage());
        }
    }

    /**
     * Make an API request to the Prometheus HTTP API using PSR-18 HTTP Client
     *
     * @param  string  $endpoint  The API endpoint
     * @param  array<string, string>  $params  Query parameters
     * @return array{'status': string, 'error'?: string, 'data': array{'result'?: array{'metric': string, 'value': ?scalar}}} The response data
     *
     * @throws Exception
     */
    private function makeApiRequest(string $endpoint, array $params = []): array
    {
        // Build the URL with query parameters
        $uri = $this->uriFactory->createUri($this->apiUrl.$endpoint);
        if (! empty($params)) {
            $uri = $uri->withQuery(http_build_query($params));
        }

        // Create the request
        $request = $this->requestFactory->createRequest('GET', $uri);

        if ($this->debug) {
            Logger::debug('Prometheus API request', [
                'url' => (string) $uri,
                'params' => $params,
            ]);
        }

        try {
            // Execute the request
            $response = $this->client->sendRequest($request);
            $httpCode = $response->getStatusCode();
            $responseBody = (string) $response->getBody();

            if ($this->debug) {
                Logger::debug('Prometheus API response', [
                    'http_code' => $httpCode,
                    'response' => $responseBody,
                ]);
            }

            // Parse the JSON response
            $data = json_decode($responseBody, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new TSDBException('Failed to parse API response: '.json_last_error_msg());
            }

            /** @var array{'status': string, 'error'?: string, 'data': array{'result'?: array{'metric': string, 'value': ?scalar}}} $data */
            return $data;
        } catch (ClientExceptionInterface $e) {
            if ($this->debug) {
                Logger::error('Prometheus API request failed: '.$e->getMessage(), [
                    'exception' => get_class($e),
                    'url' => (string) $uri,
                    'params' => $params,
                ]);
            }
            throw new TSDBException('API request failed: '.$e->getMessage());
        }
    }

    public function createDatabase(string $database): bool
    {
        // Prometheus doesn't have databases in the traditional sense
        return true;
    }

    /**
     * @return string[]
     */
    public function getDatabases(): array
    {
        // Prometheus doesn't have databases in the traditional sense
        return [];
    }

    public function close(): void
    {
        $this->connected = false;
    }

    /**
     * Check if the driver is connected to Prometheus
     */
    public function isConnected(): bool
    {
        return $this->connected;
    }

    /**
     * Set the HTTP client for API requests
     * This is primarily used for testing to inject a mock client
     */
    public function setClient(ClientInterface $client): void
    {
        $this->client = $client;
    }

    /**
     * Set the request factory for creating PSR-7 requests
     * This is primarily used for testing to inject a mock factory
     */
    public function setRequestFactory(RequestFactoryInterface $requestFactory): void
    {
        $this->requestFactory = $requestFactory;
    }

    /**
     * Set the URI factory for creating PSR-7 URIs
     * This is primarily used for testing to inject a mock factory
     */
    public function setUriFactory(UriFactoryInterface $uriFactory): void
    {
        $this->uriFactory = $uriFactory;
    }

    /**
     * Set the stream factory for creating PSR-7 streams
     * This is primarily used for testing to inject a mock factory
     */
    public function setStreamFactory(StreamFactoryInterface $streamFactory): void
    {
        $this->streamFactory = $streamFactory;
    }
}

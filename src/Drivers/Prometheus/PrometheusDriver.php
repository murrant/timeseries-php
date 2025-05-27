<?php

namespace TimeSeriesPhp\Drivers\Prometheus;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use TimeSeriesPhp\Core\AbstractTimeSeriesDB;
use TimeSeriesPhp\Core\DataPoint;
use TimeSeriesPhp\Core\QueryResult;
use TimeSeriesPhp\Core\RawQueryContract;
use TimeSeriesPhp\Exceptions\ConfigurationException;
use TimeSeriesPhp\Exceptions\ConnectionException;
use TimeSeriesPhp\Exceptions\QueryException;
use TimeSeriesPhp\Exceptions\TSDBException;

class PrometheusDriver extends AbstractTimeSeriesDB
{
    private string $apiUrl;

    private int $timeout;

    private bool $verifySSL;

    private bool $debug;

    private ?Client $client = null;

    protected function doConnect(): bool
    {
        if (! $this->config instanceof PrometheusConfig) {
            throw new ConfigurationException('Invalid configuration type. Expected PrometheusConfig.');
        }

        try {
            // Get configuration values
            $clientConfig = $this->config->getClientConfig();
            $this->apiUrl = rtrim($clientConfig['url'], '/');
            $this->timeout = $clientConfig['timeout'];
            $this->verifySSL = $clientConfig['verify_ssl'];
            $this->debug = $clientConfig['debug'];

            // Initialize the query builder
            $this->queryBuilder = new PrometheusQueryBuilder;

            // Test connection by pinging the API
            $response = $this->makeApiRequest('/api/v1/status/config');
            $this->connected = isset($response['status']) && $response['status'] === 'success';

            return $this->connected;
        } catch (Exception $e) {
            error_log('Prometheus connection failed: '.$e->getMessage());
            $this->connected = false;

            return false;
        }
    }

    public function write(DataPoint $dataPoint): bool
    {
        // Prometheus typically receives metrics via scraping or push gateway
        // This method is a placeholder as direct writes are not typically done this way
        return true;
    }

    /**
     * @throws QueryException
     */
    public function rawQuery(RawQueryContract $query): QueryResult
    {
        if (! $this->connected) {
            throw new ConnectionException('Not connected to Prometheus');
        }

        try {
            $queryString = $query->getRawQuery();

            // Extract time range from comments if present
            $timeParams = [];
            if (preg_match('/#\s*time range:\s*([^\s]+)\s+to\s+([^\s]+)/', $queryString, $matches)) {
                $timeParams['start'] = $matches[1];
                $timeParams['end'] = $matches[2];
                // Remove the comment from the query
                $queryString = preg_replace('/#\s*time range:[^\n]+/', '', $queryString);
            } elseif (preg_match('/#\s*relative time:\s*([^\n]+)/', $queryString, $matches)) {
                // Convert relative time to absolute time
                $timeParams['time'] = $matches[1];
                // Remove the comment from the query
                $queryString = preg_replace('/#\s*relative time:[^\n]+/', '', $queryString);
            }

            // Determine which API endpoint to use based on the query
            $endpoint = '/api/v1/query';
            $params = ['query' => trim($queryString)];

            // Add time parameters if present
            if (! empty($timeParams)) {
                if (isset($timeParams['start'])) {
                    $endpoint = '/api/v1/query_range';
                    $params['start'] = $timeParams['start'];
                    $params['end'] = $timeParams['end'];
                    $params['step'] = '15s'; // Default step
                } elseif (isset($timeParams['time'])) {
                    $params['time'] = $timeParams['time'];
                }
            }

            // Execute the query
            $response = $this->makeApiRequest($endpoint, $params);

            if ($response['status'] !== 'success') {
                throw new QueryException($query, 'Query execution failed: '.($response['error'] ?? 'Unknown error'));
            }

            // Process the result
            $result = [];
            if (isset($response['data']['result'])) {
                foreach ($response['data']['result'] as $item) {
                    // Format the result to match the expected format
                    $formattedItem = [];

                    // Add metric information
                    if (isset($item['metric'])) {
                        $formattedItem['metric'] = $item['metric'];
                    }

                    // Add value or values
                    if (isset($item['value'])) {
                        $formattedItem['value'] = $item['value'];
                    } elseif (isset($item['values'])) {
                        $formattedItem['values'] = $item['values'];
                    }

                    $result[] = $formattedItem;
                }
            }

            return new QueryResult($result);
        } catch (Exception $e) {
            throw new QueryException($query, 'Query execution failed: '.$e->getMessage());
        }
    }

    /**
     * Make an API request to the Prometheus HTTP API using Guzzle
     *
     * @param  string  $endpoint  The API endpoint
     * @param  array<string, string>  $params  Query parameters
     * @return array<string, mixed> The response data
     *
     * @throws Exception
     */
    private function makeApiRequest(string $endpoint, array $params = []): array
    {
        $url = $this->apiUrl.$endpoint;

        // Use injected client or create a new one
        $client = $this->client ?? new Client([
            'timeout' => $this->timeout,
            'verify' => $this->verifySSL,
        ]);

        // Prepare request options
        $options = [];
        if (! empty($params)) {
            $options['query'] = $params;
        }

        if ($this->debug) {
            $fullUrl = $url;
            if (! empty($params)) {
                $fullUrl .= '?'.http_build_query($params);
            }
            error_log("Prometheus API request: $fullUrl");
        }

        try {
            // Execute the request
            $response = $client->get($url, $options);
            $httpCode = $response->getStatusCode();
            $responseBody = (string) $response->getBody();

            if ($this->debug) {
                error_log("Prometheus API response code: $httpCode");
                error_log("Prometheus API response: $responseBody");
            }

            // Parse the JSON response
            $data = json_decode($responseBody, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new TSDBException('Failed to parse API response: '.json_last_error_msg());
            }

            return $data;
        } catch (GuzzleException $e) {
            if ($this->debug) {
                error_log('Prometheus API request failed: '.$e->getMessage());
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
    public function listDatabases(): array
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
    public function setClient(Client $client): void
    {
        $this->client = $client;
    }
}

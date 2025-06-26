<?php

namespace TimeSeriesPhp\Drivers\InfluxDB\Connection;

use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;
use TimeSeriesPhp\Contracts\Connection\ConnectionAdapterInterface;
use TimeSeriesPhp\Core\Connection\CommandResponse;
use TimeSeriesPhp\Drivers\InfluxDB\InfluxDBConfig;
use TimeSeriesPhp\Exceptions\Driver\ConnectionException;

class HttpConnectionAdapter implements ConnectionAdapterInterface
{
    private bool $connected = false;

    private ?string $orgId = null;

    public function __construct(
        private readonly InfluxDBConfig $config,
        private readonly LoggerInterface $logger,
        private readonly ClientInterface $httpClient,
        private readonly RequestFactoryInterface $requestFactory,
        private readonly StreamFactoryInterface $streamFactory
    ) {}

    public function connect(): bool
    {
        try {
            // Test connection by making a simple request
            $response = $this->makeHttpRequest('GET', '/ping');
            $this->connected = $response->success;

            $this->logger->info('Connected to InfluxDB via HTTP successfully', [
                'url' => $this->config->url,
                'org' => $this->config->org,
            ]);

            return $this->connected;
        } catch (\Throwable $e) {
            $this->logger->error('InfluxDB HTTP connection failed: '.$e->getMessage(), [
                'exception' => $e::class,
                'url' => $this->config->url,
            ]);
            $this->connected = false;

            throw new ConnectionException('Failed to connect to InfluxDB via HTTP: '.$e->getMessage(), 0, $e);
        }
    }

    public function isConnected(): bool
    {
        return $this->connected;
    }

    public function executeCommand(string $command, string $data): CommandResponse
    {
        if (! $this->isConnected()) {
            throw new ConnectionException('Not connected to InfluxDB');
        }

        try {
            // Map commands to HTTP endpoints and methods
            $endpoint = match ($command) {
                'query' => '/api/v2/query',
                'write' => '/api/v2/write',
                'health' => '/health',
                default => throw new ConnectionException("Unknown command: $command")
            };

            $method = match ($command) {
                'query', 'write' => 'POST',
                'health' => 'GET',
                default => 'GET'
            };

            // Add query parameters for specific commands
            $queryParams = [];
            if ($command === 'write') {
                $queryParams = [
                    'org' => $this->getOrgId(),
                    'bucket' => $this->config->bucket,
                    'precision' => $this->config->precision,
                ];
            }

            // Make the HTTP request
            return $this->makeHttpRequest($method, $endpoint, $data, $queryParams);
        } catch (\Throwable $e) {
            return CommandResponse::failure("Command execution failed: {$e->getMessage()}");
        }
    }

    public function close(): void
    {
        $this->connected = false;
    }

    /**
     * Make an HTTP request to the InfluxDB API
     *
     * @param  string  $method  HTTP method (GET, POST, etc.)
     * @param  string  $endpoint  API endpoint
     * @param  string  $body  Request body
     * @param  array<string, string>  $queryParams  Query parameters
     * @return CommandResponse Response from the API
     */
    private function makeHttpRequest(
        string $method,
        string $endpoint,
        string $body = '',
        array $queryParams = []
    ): CommandResponse {
        try {
            // Build the URL with query parameters
            $url = rtrim($this->config->url, '/').$endpoint;
            if (! empty($queryParams)) {
                $url .= '?'.http_build_query($queryParams);
            }

            // Create the request
            $request = $this->requestFactory->createRequest($method, $url);

            // Add headers
            $request = $request
                ->withHeader('Authorization', 'Token '.$this->config->token)
                ->withHeader('Content-Type', 'application/json');

            // Add body if needed
            if (! empty($body)) {
                $stream = $this->streamFactory->createStream($body);
                $request = $request->withBody($stream);
            }

            // Send the request
            $response = $this->httpClient->sendRequest($request);

            // Get status code
            $statusCode = $response->getStatusCode();

            // Check for success
            $success = $statusCode >= 200 && $statusCode < 300;

            // Get response body
            $responseBody = $response->getBody()->__toString();

            // Create metadata
            $headers = [];
            foreach ($response->getHeaders() as $name => $values) {
                $headers[] = $name.': '.implode(', ', $values);
            }

            $metadata = [
                'status_code' => $statusCode,
                'headers' => $headers,
            ];

            // Return the appropriate response
            if ($success) {
                return CommandResponse::success($responseBody, $metadata);
            } else {
                return CommandResponse::failure($responseBody ?: 'HTTP request failed', $metadata);
            }
        } catch (ClientExceptionInterface $e) {
            return CommandResponse::failure('HTTP client error: '.$e->getMessage());
        } catch (\Throwable $e) {
            return CommandResponse::failure('HTTP request error: '.$e->getMessage());
        }
    }

    /**
     * Get the organization ID for the configured organization name
     *
     * @return string The organization ID
     *
     * @throws ConnectionException If the organization cannot be found
     */
    private function getOrgId(): string
    {
        if ($this->orgId !== null) {
            return $this->orgId;
        }

        try {
            // Fetch organization data from API
            $response = $this->makeHttpRequest('GET', '/api/v2/orgs', '', ['org' => $this->config->org]);

            if (! $response->success) {
                throw new ConnectionException('Failed to get organization: '.($response->error ?? 'Unknown error'));
            }

            // Parse and validate response structure
            $data = json_decode($response->data, true);
            if (! is_array($data) || ! isset($data['orgs']) || ! is_array($data['orgs'])) {
                throw new ConnectionException('Invalid response format from organizations API');
            }

            // Ensure organization exists in response
            if (empty($data['orgs'])) {
                throw new ConnectionException('Organization not found: '.$this->config->org);
            }

            // Extract and validate organization ID
            $org = $data['orgs'][0];
            if (! is_array($org) || ! isset($org['id']) || ! is_string($org['id'])) {
                throw new ConnectionException('Invalid organization data: missing ID');
            }

            // Cache and return the organization ID
            $this->orgId = $org['id'];

            return $this->orgId;
        } catch (\Throwable $e) {
            // Log the error with context
            $this->logger->error('Failed to get organization ID: '.$e->getMessage(), [
                'exception' => $e::class,
                'org' => $this->config->org,
            ]);

            // Set empty string to prevent repeated API calls
            $this->orgId = '';

            // Ensure we always throw a ConnectionException
            if ($e instanceof ConnectionException) {
                throw $e;
            }

            throw new ConnectionException('Failed to get organization ID: '.$e->getMessage(), 0, $e);
        }
    }
}

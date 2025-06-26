<?php

namespace TimeSeriesPhp\Drivers\InfluxDB\Connection;

use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;
use TimeSeriesPhp\Contracts\Connection\ConnectionAdapterInterface;
use TimeSeriesPhp\Core\Connection\CommandResponse;
use TimeSeriesPhp\Drivers\InfluxDB\Connection\Command\InfluxDBHttpCommandFactory;
use TimeSeriesPhp\Drivers\InfluxDB\InfluxDBConfig;
use TimeSeriesPhp\Exceptions\Driver\ConnectionException;

class HttpConnectionAdapter implements ConnectionAdapterInterface
{
    private bool $connected = false;

    private ?string $orgId = null;

    public function __construct(
        protected readonly InfluxDBConfig $config,
        protected readonly LoggerInterface $logger,
        protected readonly ClientInterface $httpClient,
        protected readonly RequestFactoryInterface $requestFactory,
        protected readonly StreamFactoryInterface $streamFactory
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
            $commandObj = InfluxDBHttpCommandFactory::create($command, $this->config->api_version);

            $endpoint = $commandObj->getEndpoint();
            $method = $commandObj->getMethod();
            $headers = $commandObj->getHeaders();
            $queryParams = $commandObj->getQueryParams(
                $this->getOrgId(),
                $this->config->bucket,
                $this->config->precision
            );
            $processedData = $commandObj->processData($data, $this->getOrgId());

            // Make the HTTP request
            return $this->makeHttpRequest($method, $endpoint, $processedData, $queryParams, $headers);
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
     * @param  array<string, string>  $headers  Additional headers
     * @return CommandResponse Response from the API
     */
    private function makeHttpRequest(
        string $method,
        string $endpoint,
        string $body = '',
        array $queryParams = [],
        array $headers = []
    ): CommandResponse {
        try {
            $url = rtrim($this->config->url, '/').$endpoint;
            if (! empty($queryParams)) {
                $url .= '?'.http_build_query($queryParams);
            }

            $request = $this->requestFactory->createRequest($method, $url);

            // Add headers
            $request = $request
                ->withHeader('Authorization', 'Token '.$this->config->token)
                ->withHeader('Content-Type', 'application/json');

            // Add any other headers optionally override
            foreach ($headers as $name => $value) {
                $request = $request->withHeader($name, $value);
            }

            if (! empty($body)) {
                $stream = $this->streamFactory->createStream($body);
                $request = $request->withBody($stream);
            }

            $response = $this->httpClient->sendRequest($request);
            $statusCode = $response->getStatusCode();
            $success = $statusCode >= 200 && $statusCode < 300;
            $responseBody = $response->getBody()->__toString();

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
    public function getOrgId(): string
    {
        if ($this->orgId !== null) {
            return $this->orgId;
        }

        // For API v1, organization is not used, so we return the org name as is
        if ($this->config->api_version === 1) {
            $this->orgId = $this->config->org;

            return $this->orgId;
        }

        try {
            // Fetch organization data from API (v2 only)
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

            return $this->orgId ?? '';
        }
    }
}

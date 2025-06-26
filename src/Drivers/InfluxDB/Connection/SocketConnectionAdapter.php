<?php

namespace TimeSeriesPhp\Drivers\InfluxDB\Connection;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;
use TimeSeriesPhp\Core\Connection\CommandResponse;
use TimeSeriesPhp\Drivers\InfluxDB\InfluxDBConfig;
use TimeSeriesPhp\Exceptions\Driver\ConnectionException;

/**
 * Socket connection adapter for InfluxDB
 * Extends HttpConnectionAdapter because the InfluxDB UDP socket can only be used to write data
 */
class SocketConnectionAdapter extends HttpConnectionAdapter
{
    private bool $socketConnected = false;

    private ?\Socket $socket = null;

    public function __construct(
        InfluxDBConfig $config,
        LoggerInterface $logger,
        ClientInterface $httpClient,
        RequestFactoryInterface $requestFactory,
        StreamFactoryInterface $streamFactory
    ) {
        parent::__construct($config, $logger, $httpClient, $requestFactory, $streamFactory);
    }

    public function connect(): bool
    {
        try {
            // First connect via HTTP (parent method)
            parent::connect();

            // Then connect via socket
            $socketPath = $this->config->socket_path;
            if (empty($socketPath)) {
                throw new ConnectionException('Socket path is not configured');
            }

            // Create and connect the socket
            $socket = @socket_create(AF_UNIX, SOCK_STREAM, 0);
            if ($socket === false) {
                throw new ConnectionException('Failed to create socket: '.socket_strerror(socket_last_error()));
            }

            // Set the socket property
            $this->socket = $socket;

            $result = @socket_connect($this->socket, $socketPath);
            if ($result === false) {
                throw new ConnectionException('Failed to connect to socket: '.socket_strerror(socket_last_error($this->socket)));
            }

            $this->socketConnected = true;

            $this->logger->info('Connected to InfluxDB via socket successfully', [
                'socket_path' => $socketPath,
            ]);

            return true;
        } catch (\Throwable $e) {
            $this->logger->error('InfluxDB socket connection failed: '.$e->getMessage(), [
                'exception' => $e::class,
                'socket_path' => $this->config->socket_path ?? 'not set',
            ]);
            $this->socketConnected = false;

            throw new ConnectionException('Failed to connect to InfluxDB via socket: '.$e->getMessage(), 0, $e);
        }
    }

    public function isConnected(): bool
    {
        // Check both HTTP and socket connections
        return parent::isConnected() && $this->socketConnected && $this->socket !== null;
    }

    public function executeCommand(string $command, string $data): CommandResponse
    {
        if (! $this->isConnected()) {
            throw new ConnectionException('Not connected to InfluxDB');
        }

        // For write commands, use socket; for other commands, use HTTP
        if ($command === 'write') {
            return $this->executeSocketCommand($command, $data);
        }

        return parent::executeCommand($command, $data);
    }

    /**
     * Execute a command via socket connection
     */
    private function executeSocketCommand(string $command, string $data): CommandResponse
    {
        try {


            // Send the request
            $bytesSent = socket_write($this->socket, $data, strlen($data));
            if ($bytesSent === false) {
                throw new ConnectionException('Failed to send data: '.socket_strerror(socket_last_error($this->socket)));
            }

            // Read the response
            $response = '';
            $buffer = '';

            while (socket_recv($this->socket, $buffer, 4096, 0) > 0) {
                $response .= (string)$buffer;
                if (str_ends_with((string)$buffer, "\n")) {
                    break;
                }
            }

            // Parse the response
            /** @var array<string, mixed>|null $responseData */
            $responseData = json_decode($response, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return CommandResponse::failure('Failed to parse response: '.json_last_error_msg());
            }

            if (! is_array($responseData)) {
                return CommandResponse::failure('Invalid response format');
            }

            if (isset($responseData['error']) && is_string($responseData['error'])) {
                $metadata = isset($responseData['metadata']) && is_array($responseData['metadata'])
                    ? $responseData['metadata']
                    : [];

                return CommandResponse::failure($responseData['error'], $metadata);
            }

            $responseText = isset($responseData['data']) && is_string($responseData['data'])
                ? $responseData['data']
                : '';
            $metadata = isset($responseData['metadata']) && is_array($responseData['metadata'])
                ? $responseData['metadata']
                : [];

            return CommandResponse::success($responseText, $metadata);
        } catch (\Throwable $e) {
            $this->logger->error('Socket command execution failed: '.$e->getMessage(), [
                'exception' => $e::class,
                'command' => $command,
            ]);
            return CommandResponse::failure("Socket command execution failed: {$e->getMessage()}");
        }
    }

    public function close(): void
    {
        // Close the socket connection
        if ($this->socket instanceof \Socket) {
            socket_close($this->socket);
            $this->socket = null;
        }
        $this->socketConnected = false;

        // Close the HTTP connection (parent)
        parent::close();
    }
}

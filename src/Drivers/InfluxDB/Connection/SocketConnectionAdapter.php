<?php

namespace TimeSeriesPhp\Drivers\InfluxDB\Connection;

use Psr\Log\LoggerInterface;
use TimeSeriesPhp\Contracts\Connection\ConnectionAdapterInterface;
use TimeSeriesPhp\Core\Connection\CommandResponse;
use TimeSeriesPhp\Drivers\InfluxDB\InfluxDBConfig;
use TimeSeriesPhp\Exceptions\Driver\ConnectionException;

class SocketConnectionAdapter implements ConnectionAdapterInterface
{
    private bool $connected = false;

    private ?\Socket $socket = null;

    public function __construct(
        private readonly InfluxDBConfig $config,
        private readonly LoggerInterface $logger
    ) {}

    public function connect(): bool
    {
        try {
            // Parse socket path from config
            $socketPath = $this->config->socket_path;
            if (empty($socketPath) || ! is_string($socketPath)) {
                throw new ConnectionException('Socket path is not configured');
            }

            // Create and connect the socket
            $this->socket = @socket_create(AF_UNIX, SOCK_STREAM, 0);
            if ($this->socket === false) {
                throw new ConnectionException('Failed to create socket: '.socket_strerror(socket_last_error()));
            }

            // Ensure socket is valid
            if (! $this->socket instanceof \Socket) {
                throw new ConnectionException('Socket is not valid');
            }

            $result = @socket_connect($this->socket, $socketPath);
            if ($result === false) {
                throw new ConnectionException('Failed to connect to socket: '.socket_strerror(socket_last_error($this->socket)));
            }

            $this->connected = true;

            $this->logger->info('Connected to InfluxDB via socket successfully', [
                'socket_path' => $socketPath,
            ]);

            return true;
        } catch (\Throwable $e) {
            $this->logger->error('InfluxDB socket connection failed: '.$e->getMessage(), [
                'exception' => $e::class,
                'socket_path' => $this->config->socket_path ?? 'not set',
            ]);
            $this->connected = false;

            throw new ConnectionException('Failed to connect to InfluxDB via socket: '.$e->getMessage(), 0, $e);
        }
    }

    public function isConnected(): bool
    {
        return $this->connected && $this->socket !== null;
    }

    public function executeCommand(string $command, string $data): CommandResponse
    {
        if (! $this->isConnected()) {
            throw new ConnectionException('Not connected to InfluxDB');
        }

        try {
            // Format the command for socket communication
            $request = json_encode([
                'command' => $command,
                'org' => $this->config->org,
                'bucket' => $this->config->bucket,
                'data' => $data,
            ])."\n";

            // Ensure socket is valid
            if (! $this->socket instanceof \Socket) {
                throw new ConnectionException('Socket is not valid');
            }

            // Send the request
            $bytesSent = socket_write($this->socket, $request, strlen($request));
            if ($bytesSent === false) {
                throw new ConnectionException('Failed to send data: '.socket_strerror(socket_last_error($this->socket)));
            }

            // Read the response
            $response = '';
            $buffer = '';

            // Ensure socket is valid
            if (! $this->socket instanceof \Socket) {
                throw new ConnectionException('Socket is not valid');
            }

            while (socket_recv($this->socket, $buffer, 4096, 0) > 0) {
                $response .= $buffer;
                if (is_string($buffer) && str_ends_with($buffer, "\n")) {
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

            $data = isset($responseData['data']) && is_string($responseData['data'])
                ? $responseData['data']
                : '';
            $metadata = isset($responseData['metadata']) && is_array($responseData['metadata'])
                ? $responseData['metadata']
                : [];

            return CommandResponse::success($data, $metadata);
        } catch (\Throwable $e) {
            return CommandResponse::failure("Command execution failed: {$e->getMessage()}");
        }
    }

    public function close(): void
    {
        if ($this->socket instanceof \Socket) {
            socket_close($this->socket);
            $this->socket = null;
        }
        $this->connected = false;
    }
}

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

    private $socket = null;

    public function __construct(
        private readonly InfluxDBConfig $config,
        private readonly LoggerInterface $logger
    ) {}

    public function connect(): bool
    {
        try {
            // Parse socket path from config
            $socketPath = $this->config->socket_path;
            if (empty($socketPath)) {
                throw new ConnectionException('Socket path is not configured');
            }

            // Create and connect the socket
            $this->socket = @socket_create(AF_UNIX, SOCK_STREAM, 0);
            if ($this->socket === false) {
                throw new ConnectionException('Failed to create socket: '.socket_strerror(socket_last_error()));
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

            // Send the request
            $bytesSent = socket_write($this->socket, $request, strlen($request));
            if ($bytesSent === false) {
                throw new ConnectionException('Failed to send data: '.socket_strerror(socket_last_error($this->socket)));
            }

            // Read the response
            $response = '';
            $buffer = '';
            while (socket_recv($this->socket, $buffer, 4096, 0) > 0) {
                $response .= $buffer;
                if (str_ends_with($buffer, "\n")) {
                    break;
                }
            }

            // Parse the response
            $responseData = json_decode($response, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return CommandResponse::failure('Failed to parse response: '.json_last_error_msg());
            }

            if (isset($responseData['error'])) {
                return CommandResponse::failure($responseData['error'], $responseData['metadata'] ?? []);
            }

            return CommandResponse::success($responseData['data'] ?? '', $responseData['metadata'] ?? []);
        } catch (\Throwable $e) {
            return CommandResponse::failure("Command execution failed: {$e->getMessage()}");
        }
    }

    public function close(): void
    {
        if ($this->socket !== null) {
            socket_close($this->socket);
            $this->socket = null;
        }
        $this->connected = false;
    }
}

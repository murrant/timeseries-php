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
 * UDP Socket connection adapter for InfluxDB
 * Extends HttpConnectionAdapter because the InfluxDB UDP socket can only be used to write data
 */
class UdpConnectionAdapter extends HttpConnectionAdapter
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

            // Extract host from URL
            $url = parse_url($this->config->url);
            $host = $url['host'] ?? 'localhost';
            $port = 8089; // Default UDP port for InfluxDB 1.x

            // Create and connect the socket
            $socket = @socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
            if ($socket === false) {
                throw new ConnectionException('Failed to create socket: '.socket_strerror(socket_last_error()));
            }

            // Set the socket property
            $this->socket = $socket;

            $result = @socket_connect($this->socket, $host, $port);
            if ($result === false) {
                throw new ConnectionException('Failed to connect to socket: '.socket_strerror(socket_last_error($this->socket)));
            }

            $this->socketConnected = true;

            $this->logger->info('Connected to InfluxDB via UDP socket successfully', [
                'host' => $host,
                'port' => $port,
            ]);

            return true;
        } catch (\Throwable $e) {
            $this->logger->error('InfluxDB UDP socket connection failed: '.$e->getMessage(), [
                'exception' => $e::class,
                'url' => $this->config->url,
                'port' => $this->config->udp_port,
            ]);
            $this->socketConnected = false;

            throw new ConnectionException('Failed to connect to InfluxDB via UDP socket: '.$e->getMessage(), 0, $e);
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
            if ($this->socket === null) {
                throw new ConnectionException('UDP socket is not connected');
            }

            // Send the request
            $bytesSent = socket_write($this->socket, $data, strlen($data));
            if ($bytesSent === false) {
                throw new ConnectionException('Failed to send data: '.socket_strerror(socket_last_error($this->socket)));
            }

            // UDP is connectionless
            return CommandResponse::success();
        } catch (\Throwable $e) {
            $this->logger->error('UDP socket command execution failed: '.$e->getMessage(), [
                'exception' => $e::class,
                'command' => $command,
            ]);

            return CommandResponse::failure("UDP socket command execution failed: {$e->getMessage()}");
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

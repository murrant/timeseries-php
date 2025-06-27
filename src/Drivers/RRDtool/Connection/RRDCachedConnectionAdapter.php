<?php

namespace TimeSeriesPhp\Drivers\RRDtool\Connection;

use Psr\Log\LoggerInterface;
use TimeSeriesPhp\Contracts\Connection\ConnectionAdapterInterface;
use TimeSeriesPhp\Core\Connection\CommandResponse;
use TimeSeriesPhp\Drivers\RRDtool\Exception\RRDtoolException;
use TimeSeriesPhp\Drivers\RRDtool\Factory\ProcessFactoryInterface;
use TimeSeriesPhp\Drivers\RRDtool\RRDtoolConfig;
use TimeSeriesPhp\Exceptions\Driver\ConnectionException;

/**
 * Connection adapter for direct connection to RRDcached
 */
class RRDCachedConnectionAdapter implements ConnectionAdapterInterface
{
    protected bool $connected = false;
    
    private $socket = null;

    public function __construct(
        protected readonly RRDtoolConfig $config,
        protected readonly ProcessFactoryInterface $processFactory,
        protected readonly LoggerInterface $logger,
    ) {}

    /**
     * Connect to the database
     *
     * @return bool True if connection was successful, false otherwise
     * @throws ConnectionException
     */
    public function connect(): bool
    {
        try {
            // Verify RRD directory exists and is writable
            if (!is_dir($this->config->rrd_dir)) {
                if (!mkdir($this->config->rrd_dir, 0755, true)) {
                    throw new ConnectionException("Cannot create RRD directory: {$this->config->rrd_dir}");
                }
            }

            if (!is_writable($this->config->rrd_dir)) {
                throw new ConnectionException("RRD directory is not writable: {$this->config->rrd_dir}");
            }

            // Verify rrdcached address is set
            if (empty($this->config->rrdcached_address)) {
                throw new ConnectionException('rrdcached address must be specified for RRDCachedConnectionAdapter');
            }

            // Parse the rrdcached address
            $address = $this->config->rrdcached_address;
            
            // Handle different address formats
            if (str_starts_with($address, 'unix:')) {
                // Unix socket
                $socketPath = substr($address, 5);
                $this->socket = @socket_create(AF_UNIX, SOCK_STREAM, 0);
                if (!$this->socket) {
                    throw new ConnectionException('Failed to create Unix socket: ' . socket_strerror(socket_last_error()));
                }
                
                if (!@socket_connect($this->socket, $socketPath)) {
                    throw new ConnectionException('Failed to connect to RRDcached Unix socket: ' . socket_strerror(socket_last_error()));
                }
            } elseif (str_starts_with($address, 'tcp:')) {
                // TCP socket
                $tcpAddress = substr($address, 4);
                $parts = explode(':', $tcpAddress);
                if (count($parts) !== 2) {
                    throw new ConnectionException('Invalid TCP address format for RRDcached: ' . $address);
                }
                
                $host = $parts[0];
                $port = (int) $parts[1];
                
                $this->socket = @socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
                if (!$this->socket) {
                    throw new ConnectionException('Failed to create TCP socket: ' . socket_strerror(socket_last_error()));
                }
                
                if (!@socket_connect($this->socket, $host, $port)) {
                    throw new ConnectionException('Failed to connect to RRDcached TCP socket: ' . socket_strerror(socket_last_error()));
                }
            } else {
                // Try to guess the format
                if (str_contains($address, ':')) {
                    // Assume TCP
                    $parts = explode(':', $address);
                    if (count($parts) !== 2) {
                        throw new ConnectionException('Invalid TCP address format for RRDcached: ' . $address);
                    }
                    
                    $host = $parts[0];
                    $port = (int) $parts[1];
                    
                    $this->socket = @socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
                    if (!$this->socket) {
                        throw new ConnectionException('Failed to create TCP socket: ' . socket_strerror(socket_last_error()));
                    }
                    
                    if (!@socket_connect($this->socket, $host, $port)) {
                        throw new ConnectionException('Failed to connect to RRDcached TCP socket: ' . socket_strerror(socket_last_error()));
                    }
                } else {
                    // Assume Unix socket
                    $this->socket = @socket_create(AF_UNIX, SOCK_STREAM, 0);
                    if (!$this->socket) {
                        throw new ConnectionException('Failed to create Unix socket: ' . socket_strerror(socket_last_error()));
                    }
                    
                    if (!@socket_connect($this->socket, $address)) {
                        throw new ConnectionException('Failed to connect to RRDcached Unix socket: ' . socket_strerror(socket_last_error()));
                    }
                }
            }
            
            // Set socket timeout
            socket_set_option($this->socket, SOL_SOCKET, SO_RCVTIMEO, ['sec' => $this->config->command_timeout, 'usec' => 0]);
            socket_set_option($this->socket, SOL_SOCKET, SO_SNDTIMEO, ['sec' => $this->config->command_timeout, 'usec' => 0]);
            
            // Test the connection by sending a PING command
            $response = $this->sendCommand('PING');
            if (!str_starts_with($response, '0 ')) {
                throw new ConnectionException('Failed to ping RRDcached: ' . $response);
            }

            $this->connected = true;

            $this->logger->info('Connected to RRDcached successfully', [
                'rrd_dir' => $this->config->rrd_dir,
                'rrdcached_address' => $this->config->rrdcached_address,
                'adapter' => self::class,
            ]);

            return true;
        } catch (\Throwable $e) {
            $this->logger->error('Failed to connect to RRDcached: ' . $e->getMessage(), [
                'exception' => $e::class,
                'rrdcached_address' => $this->config->rrdcached_address,
                'rrd_dir' => $this->config->rrd_dir,
            ]);

            // Clean up if socket was created
            if ($this->socket) {
                socket_close($this->socket);
                $this->socket = null;
            }
            
            $this->connected = false;

            throw new ConnectionException('Failed to connect to RRDcached: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Check if connected to the database
     *
     * @return bool True if connected, false otherwise
     */
    public function isConnected(): bool
    {
        return $this->connected && $this->socket !== null;
    }

    /**
     * Execute a command on the database
     *
     * @param string $command The command to execute (e.g., 'create', 'update', 'fetch')
     * @param string $data The data to send with the command (arguments as a JSON string)
     * @return CommandResponse The response from the database
     * @throws ConnectionException If not connected
     */
    public function executeCommand(string $command, string $data): CommandResponse
    {
        if (!$this->isConnected()) {
            throw new ConnectionException('Not connected to RRDcached');
        }

        try {
            // Parse the arguments from JSON
            $args = json_decode($data, true);
            if (!is_array($args)) {
                return CommandResponse::failure('Invalid command arguments: expected JSON array');
            }

            // Map RRDtool commands to RRDcached protocol commands
            $rrdcachedCommand = $this->mapToRRDcachedCommand($command, $args);
            
            if ($this->config->debug) {
                $this->logger->debug('Running RRDcached command', [
                    'command' => $command,
                    'rrdcached_command' => $rrdcachedCommand,
                    'args' => $args,
                ]);
            }

            // Send the command to RRDcached
            $response = $this->sendCommand($rrdcachedCommand);
            
            // Parse the response
            $lines = explode("\n", $response);
            $statusLine = array_shift($lines);
            
            // RRDcached protocol: first line is "<status code> <message>"
            if (!preg_match('/^(\d+)\s+(.*)$/', $statusLine, $matches)) {
                return CommandResponse::failure('Invalid response from RRDcached: ' . $statusLine);
            }
            
            $statusCode = (int) $matches[1];
            $statusMessage = $matches[2];
            
            // Status codes < 0 indicate errors
            if ($statusCode < 0) {
                return CommandResponse::failure($statusMessage, [
                    'status_code' => $statusCode,
                    'command' => $command,
                    'rrdcached_command' => $rrdcachedCommand,
                ]);
            }
            
            // Combine the remaining lines as the response data
            $responseData = implode("\n", $lines);
            
            return CommandResponse::success($responseData, [
                'status_code' => $statusCode,
                'status_message' => $statusMessage,
                'command' => $command,
                'rrdcached_command' => $rrdcachedCommand,
            ]);
        } catch (RRDtoolException $e) {
            return CommandResponse::failure($e->getDebugMessage($this->config->debug), [
                'command' => $command,
                'exception' => $e::class,
            ]);
        } catch (\Throwable $e) {
            return CommandResponse::failure("Command execution failed: {$e->getMessage()}", [
                'command' => $command,
                'exception' => $e::class,
            ]);
        }
    }

    /**
     * Close the connection to the database
     */
    public function close(): void
    {
        if ($this->socket) {
            socket_close($this->socket);
            $this->socket = null;
        }
        
        $this->connected = false;
    }
    
    /**
     * Send a command to the RRDcached server
     *
     * @param string $command The command to send
     * @return string The response from the server
     * @throws ConnectionException If the command fails
     */
    private function sendCommand(string $command): string
    {
        // Add newline to command
        $command .= "\n";
        
        // Send the command
        $result = socket_write($this->socket, $command, strlen($command));
        if ($result === false) {
            throw new ConnectionException('Failed to send command to RRDcached: ' . socket_strerror(socket_last_error($this->socket)));
        }
        
        // Read the response
        $response = '';
        $buffer = '';
        
        // RRDcached protocol ends responses with a newline
        while (($bytes = socket_recv($this->socket, $buffer, 4096, 0)) > 0) {
            $response .= $buffer;
            
            // Check if we've received the complete response
            if (str_ends_with($response, "\n")) {
                break;
            }
        }
        
        if ($bytes === false) {
            throw new ConnectionException('Failed to read response from RRDcached: ' . socket_strerror(socket_last_error($this->socket)));
        }
        
        return rtrim($response, "\n");
    }
    
    /**
     * Map RRDtool commands to RRDcached protocol commands
     *
     * @param string $command The RRDtool command
     * @param array<string> $args The command arguments
     * @return string The RRDcached protocol command
     */
    private function mapToRRDcachedCommand(string $command, array $args): string
    {
        // RRDcached protocol commands are uppercase
        $command = strtoupper($command);
        
        switch ($command) {
            case 'CREATE':
                // CREATE filename [--step|-s step] [--start|-b start time] [--no-overwrite|-O] DS definitions RRA definitions
                return $command . ' ' . implode(' ', $args);
                
            case 'UPDATE':
                // UPDATE filename timestamp:value[:value]...
                return $command . ' ' . implode(' ', $args);
                
            case 'FETCH':
                // FETCH filename CF [--start|-s start] [--end|-e end] [--resolution|-r resolution]
                return $command . ' ' . implode(' ', $args);
                
            case 'FLUSH':
                // FLUSH filename
                return $command . ' ' . implode(' ', $args);
                
            case 'FORGET':
                // FORGET filename
                return $command . ' ' . implode(' ', $args);
                
            case 'QUEUE':
                // QUEUE
                return $command;
                
            case 'PENDING':
                // PENDING filename
                return $command . ' ' . implode(' ', $args);
                
            case 'INFO':
                // INFO filename
                return $command . ' ' . implode(' ', $args);
                
            case 'FIRST':
                // FIRST filename [--rraindex|-i rraindex]
                return $command . ' ' . implode(' ', $args);
                
            case 'LAST':
                // LAST filename
                return $command . ' ' . implode(' ', $args);
                
            default:
                // For commands not directly supported by RRDcached, we'll use the FLUSHALL command
                // to ensure any pending updates are written, then execute the command locally
                return 'FLUSHALL';
        }
    }
}
<?php

namespace TimeSeriesPhp\Drivers\RRDtool\Connection;

use Psr\Log\LoggerInterface;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use TimeSeriesPhp\Contracts\Connection\ConnectionAdapterInterface;
use TimeSeriesPhp\Core\Connection\CommandResponse;
use TimeSeriesPhp\Drivers\RRDtool\Exception\RRDtoolCommandTimeoutException;
use TimeSeriesPhp\Drivers\RRDtool\Exception\RRDtoolException;
use TimeSeriesPhp\Drivers\RRDtool\Factory\ProcessFactoryInterface;
use TimeSeriesPhp\Drivers\RRDtool\RRDtoolConfig;
use TimeSeriesPhp\Exceptions\Driver\ConnectionException;

/**
 * Connection adapter for local RRDtool execution
 */
class LocalConnectionAdapter implements ConnectionAdapterInterface
{
    protected bool $connected = false;

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

            // Verify RRDtool is executable
            $process = $this->processFactory->create([$this->config->rrdtool_path, '--version']);
            $process->setTimeout($this->config->command_timeout);
            $process->run();

            if (!$process->isSuccessful()) {
                throw new ConnectionException("RRDtool executable not found or not executable: {$this->config->rrdtool_path}");
            }

            $this->connected = true;

            $this->logger->info('Connected to RRDtool successfully', [
                'rrd_dir' => $this->config->rrd_dir,
                'rrdtool_path' => $this->config->rrdtool_path,
                'adapter' => self::class,
            ]);

            return true;
        } catch (\Throwable $e) {
            $this->logger->error('Failed to connect to RRDtool: ' . $e->getMessage(), [
                'exception' => $e::class,
                'rrdtool_path' => $this->config->rrdtool_path,
                'rrd_dir' => $this->config->rrd_dir,
            ]);

            throw new ConnectionException('Failed to connect to RRDtool: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Check if connected to the database
     *
     * @return bool True if connected, false otherwise
     */
    public function isConnected(): bool
    {
        return $this->connected;
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
            throw new ConnectionException('Not connected to RRDtool');
        }

        try {
            // Parse the arguments from JSON
            $args = json_decode($data, true);
            if (!is_array($args)) {
                return CommandResponse::failure('Invalid command arguments: expected JSON array');
            }

            // Build the command
            array_unshift($args, $command);
            array_unshift($args, $this->config->rrdtool_path);

            // Execute the command
            $process = $this->processFactory->create($args);
            $process->setTimeout($this->config->command_timeout);

            if ($this->config->debug) {
                $this->logger->debug('Running RRDtool command', [
                    'command' => $command,
                    'args' => $args,
                    'full_command' => implode(' ', $args),
                ]);
            }

            try {
                $process->run();

                if (!$process->isSuccessful()) {
                    $errorOutput = $process->getErrorOutput();
                    return CommandResponse::failure($errorOutput ?: 'Command execution failed', [
                        'exit_code' => $process->getExitCode(),
                        'command' => $command,
                        'args' => $args,
                    ]);
                }

                return CommandResponse::success($process->getOutput(), [
                    'exit_code' => $process->getExitCode(),
                    'command' => $command,
                ]);
            } catch (ProcessTimedOutException) {
                throw new RRDtoolCommandTimeoutException($command, $args);
            }
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
        $this->connected = false;
    }
}
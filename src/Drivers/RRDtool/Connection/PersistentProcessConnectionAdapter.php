<?php

namespace TimeSeriesPhp\Drivers\RRDtool\Connection;

use Psr\Log\LoggerInterface;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process;
use TimeSeriesPhp\Contracts\Connection\ConnectionAdapterInterface;
use TimeSeriesPhp\Core\Connection\CommandResponse;
use TimeSeriesPhp\Drivers\RRDtool\Exception\RRDtoolCommandTimeoutException;
use TimeSeriesPhp\Drivers\RRDtool\Exception\RRDtoolException;
use TimeSeriesPhp\Drivers\RRDtool\Factory\ProcessFactoryInterface;
use TimeSeriesPhp\Drivers\RRDtool\RRDtoolConfig;
use TimeSeriesPhp\Exceptions\Driver\ConnectionException;

/**
 * Connection adapter for RRDtool via persistent process
 */
class PersistentProcessConnectionAdapter implements ConnectionAdapterInterface
{
    protected bool $connected = false;

    private ?Process $persistentProcess = null;

    private ?InputStream $persistentInput = null;

    public function __construct(
        protected readonly RRDtoolConfig $config,
        protected readonly ProcessFactoryInterface $processFactory,
        protected readonly LoggerInterface $logger,
    ) {}

    /**
     * Connect to the database
     *
     * @return bool True if connection was successful, false otherwise
     *
     * @throws ConnectionException
     */
    public function connect(): bool
    {
        try {
            // Verify RRD directory exists and is writable
            if (! is_dir($this->config->rrd_dir)) {
                if (! mkdir($this->config->rrd_dir, 0755, true)) {
                    throw new ConnectionException("Cannot create RRD directory: {$this->config->rrd_dir}");
                }
            }

            if (! is_writable($this->config->rrd_dir)) {
                throw new ConnectionException("RRD directory is not writable: {$this->config->rrd_dir}");
            }

            // Start the persistent process
            $this->persistentProcess = $this->processFactory->create([$this->config->rrdtool_path, '-']);
            $this->persistentInput = new InputStream;
            $this->persistentProcess->setInput($this->persistentInput);
            $this->persistentProcess->setTimeout(null); // No timeout for the process itself
            $this->persistentProcess->start();

            // Verify the process started successfully
            if (! $this->persistentProcess->isRunning()) {
                throw new ConnectionException("Failed to start persistent RRDtool process: {$this->config->rrdtool_path}");
            }

            $this->connected = true;

            $this->logger->info('Connected to RRDtool via persistent process successfully', [
                'rrd_dir' => $this->config->rrd_dir,
                'rrdtool_path' => $this->config->rrdtool_path,
                'adapter' => self::class,
            ]);

            return true;
        } catch (\Throwable $e) {
            $this->logger->error('Failed to connect to RRDtool via persistent process: '.$e->getMessage(), [
                'exception' => $e::class,
                'rrdtool_path' => $this->config->rrdtool_path,
                'rrd_dir' => $this->config->rrd_dir,
            ]);

            // Clean up if process was started
            if ($this->persistentProcess !== null && $this->persistentProcess->isRunning()) {
                $this->persistentProcess->stop();
            }

            $this->persistentProcess = null;
            $this->persistentInput = null;
            $this->connected = false;

            throw new ConnectionException('Failed to connect to RRDtool via persistent process: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * Check if connected to the database
     *
     * @return bool True if connected, false otherwise
     */
    public function isConnected(): bool
    {
        return $this->connected &&
               $this->persistentProcess?->isRunning() === true;
    }

    /**
     * Execute a command on the database
     *
     * @param  string  $command  The command to execute (e.g., 'create', 'update', 'fetch')
     * @param  string  $data  The data to send with the command (arguments as a JSON string)
     * @return CommandResponse The response from the database
     *
     * @throws ConnectionException If not connected
     */
    public function executeCommand(string $command, string $data): CommandResponse
    {
        if (! $this->isConnected()) {
            throw new ConnectionException('Not connected to RRDtool via persistent process');
        }

        if ($this->persistentInput === null) {
            throw new ConnectionException('Persistent process input stream is not available');
        }

        try {
            // Parse the arguments from JSON
            $args = json_decode($data, true);
            if (! is_array($args)) {
                return CommandResponse::failure('Invalid command arguments: expected JSON array');
            }

            // Build the command
            array_unshift($args, $command);
            $commandString = implode(' ', $args);

            if ($this->config->debug) {
                $this->logger->debug('Running RRDtool command via persistent process', [
                    'command' => $command,
                    'args' => $args,
                    'full_command' => $commandString,
                ]);
            }

            // Send the command to the process
            $this->persistentInput->write($commandString."\n");

            // Wait for the response with timeout
            $timeout = time() + $this->config->command_timeout;
            $output = '';
            $error = '';

            try {
                if ($this->persistentProcess === null) {
                    throw new ConnectionException('Persistent process is not available');
                }

                $stringArgs = [];
                foreach ($args as $arg) {
                    // Ensure $arg can be safely cast to string
                    if (is_scalar($arg) || is_null($arg) || (is_object($arg) && method_exists($arg, '__toString'))) {
                        $stringArgs[] = (string) $arg;
                    } else {
                        $stringArgs[] = '';
                    }
                }

                $this->persistentProcess->waitUntil(function (string $type, string $buffer) use (&$output, &$error, $timeout, $command, $stringArgs) {
                    if ($type === Process::OUT) {
                        $output .= $buffer;
                        if (preg_match('/^OK/m', $buffer)) {
                            return true;
                        }
                    } elseif ($type === Process::ERR) {
                        $error .= $buffer;
                        if (str_starts_with($buffer, 'ERROR:')) {
                            return true;
                        }
                    }

                    if (time() > $timeout) {
                        throw new RRDtoolCommandTimeoutException($command, $stringArgs);
                    }

                    return false;
                });
            } catch (ProcessTimedOutException) {
                $stringArgs = [];
                foreach ($args as $arg) {
                    // Ensure $arg can be safely cast to string
                    if (is_scalar($arg) || is_null($arg) || (is_object($arg) && method_exists($arg, '__toString'))) {
                        $stringArgs[] = (string) $arg;
                    } else {
                        $stringArgs[] = '';
                    }
                }
                throw new RRDtoolCommandTimeoutException($command, $stringArgs);
            }

            // Process the response
            if (! empty($error) || preg_match('/^ERROR:/m', $output)) {
                $errorMessage = ! empty($error) ? $error : $output;

                return CommandResponse::failure($errorMessage, [
                    'command' => $command,
                    'args' => $args,
                ]);
            }

            // Trim the "OK" line from the output
            $lastLinePos = strrpos($output, "\n", -2);
            if ($lastLinePos !== false) {
                $output = substr($output, 0, $lastLinePos);
            }

            // Clear the output buffer for the next command
            $this->persistentProcess->clearOutput();

            return CommandResponse::success($output, [
                'command' => $command,
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
        if ($this->persistentProcess !== null && $this->persistentProcess->isRunning()) {
            $this->persistentProcess->stop();
        }

        $this->persistentProcess = null;
        $this->persistentInput = null;
        $this->connected = false;
    }
}

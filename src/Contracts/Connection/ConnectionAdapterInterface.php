<?php

namespace TimeSeriesPhp\Contracts\Connection;

use TimeSeriesPhp\Core\Connection\CommandResponse;

interface ConnectionAdapterInterface
{
    /**
     * Connect to the database
     *
     * @return bool True if connection was successful, false otherwise
     */
    public function connect(): bool;

    /**
     * Check if connected to the database
     *
     * @return bool True if connected, false otherwise
     */
    public function isConnected(): bool;

    /**
     * Execute a command on the database
     *
     * @param  string  $command  The command to execute
     * @param  string  $data  The data to send with the command
     * @return CommandResponse The response from the database
     */
    public function executeCommand(string $command, string $data): CommandResponse;

    /**
     * Close the connection to the database
     */
    public function close(): void;
}

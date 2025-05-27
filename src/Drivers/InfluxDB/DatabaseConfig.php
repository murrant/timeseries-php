<?php

namespace TimeSeriesPhp\Drivers\InfluxDB;

use TimeSeriesPhp\Config\AbstractDriverConfig;

class DatabaseConfig extends AbstractDriverConfig
{
    protected string $driverName = 'influxdb';
    protected array $defaults = [
        'host' => 'localhost',
        'port' => null,
        'database' => 'default',
        'username' => null,
        'password' => null,
        'timeout' => 30,
        'retry_attempts' => 3,
        'retry_delay' => 1000, // milliseconds
        'ssl' => false,
        'verify_ssl' => true,
    ];

    protected array $required = ['host'];

    public function __construct(array $config = [])
    {
        $this->addValidator('port', fn ($port) => is_null($port) || (is_int($port) && $port > 0 && $port <= 65535));
        $this->addValidator('timeout', fn ($timeout) => is_int($timeout) && $timeout > 0);
        $this->addValidator('retry_attempts', fn ($attempts) => is_int($attempts) && $attempts >= 0);
        $this->addValidator('retry_delay', fn ($delay) => is_int($delay) && $delay >= 0);

        parent::__construct($config);
    }

    public function getConnectionString(): string
    {
        $host = $this->get('host');
        $port = $this->get('port');
        $database = $this->get('database');

        $dsn = $host;
        if ($port) {
            $dsn .= ':'.$port;
        }
        if ($database) {
            $dsn .= '/'.$database;
        }

        return $dsn;
    }

    public function hasAuth(): bool
    {
        return ! empty($this->get('username'));
    }

    public function getAuthCredentials(): array
    {
        return [
            'username' => $this->get('username'),
            'password' => $this->get('password'),
        ];
    }
}

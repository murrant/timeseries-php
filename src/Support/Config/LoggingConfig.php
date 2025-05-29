<?php

namespace TimeSeriesPhp\Support\Config;

use TimeSeriesPhp\Exceptions\Config\ConfigurationException;
use TimeSeriesPhp\Support\Logs\LogLevel;

class LoggingConfig extends AbstractConfig
{
    /**
     * @var array<string, mixed>
     */
    protected array $defaults = [
        'enabled' => true,
        'level' => 'warning', // debug, info, warning, error
        'channels' => ['queries', 'connections', 'errors'],
        'log_to_file' => false,
        'log_file' => '/var/log/tsdb.log',
        'log_to_stderr' => false,
        'log_to_syslog' => false,
        'log_to_error_log' => true,
        'format' => '[{timestamp}] {level}: {message} {context}',
        'include_stack_trace' => false,
    ];

    /**
     * @param  array<string, mixed>  $config
     *
     * @throws ConfigurationException
     */
    public function __construct(array $config = [])
    {
        $this->addValidator('level', fn ($level) => $level instanceof LogLevel || (is_string($level) && LogLevel::tryFrom($level) !== null));
        $this->addValidator('log_file', fn ($path) => is_string($path) && ! empty($path));

        parent::__construct($config);
    }

    public function isEnabled(): bool
    {
        return $this->getBool('enabled');
    }

    public function shouldLogChannel(string $channel): bool
    {
        return in_array($channel, $this->getArray('channels'));
    }

    /**
     * Check if a log level is enabled
     *
     * @param  LogLevel  $level  The log level to check
     * @return bool True if the level is enabled
     */
    public function isLevelEnabled(LogLevel $level): bool
    {
        if (! $this->isEnabled()) {
            return false;
        }

        $configLevel = LogLevel::tryFrom($this->getString('level')) ?? LogLevel::INFO;

        return $level->isGreaterOrEqualTo($configLevel);
    }
}

<?php

namespace TimeSeriesPhp\Config;

use TimeSeriesPhp\Exceptions\ConfigurationException;

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
        $this->addValidator('level', fn ($level) => in_array($level, ['debug', 'info', 'warning', 'error']));
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
     * @param  string  $level  The log level to check
     * @return bool True if the level is enabled
     */
    public function isLevelEnabled(string $level): bool
    {
        if (! $this->isEnabled()) {
            return false;
        }

        $configLevel = $this->getString('level');

        // Map levels to numeric values for comparison
        $levels = [
            'debug' => 0,
            'info' => 1,
            'warning' => 2,
            'error' => 3,
        ];

        // If the level is not recognized, default to info
        $levelValue = $levels[$level] ?? 1;
        $configLevelValue = $levels[$configLevel] ?? 1;

        // Level is enabled if it's greater than or equal to the configured level
        return $levelValue >= $configLevelValue;
    }
}

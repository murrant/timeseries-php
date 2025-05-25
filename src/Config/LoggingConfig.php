<?php

namespace TimeSeriesPhp\Config;

class LoggingConfig extends AbstractConfig
{
    protected array $defaults = [
        'enabled' => false,
        'level' => 'info', // debug, info, warning, error
        'channels' => ['queries', 'connections', 'errors'],
        'output' => 'file', // file, syslog, stdout
        'file' => [
            'path' => '/var/log/tsdb.log',
            'max_size' => '10MB',
            'rotate' => 5
        ],
        'format' => '[{timestamp}] {level}: {message} {context}',
        'include_stack_trace' => false
    ];

    public function __construct(array $config = [])
    {
        $this->addValidator('level', fn($level) => in_array($level, ['debug', 'info', 'warning', 'error']));
        $this->addValidator('output', fn($output) => in_array($output, ['file', 'syslog', 'stdout']));

        parent::__construct($config);
    }

    public function isEnabled(): bool
    {
        return $this->get('enabled', false);
    }

    public function shouldLogChannel(string $channel): bool
    {
        return in_array($channel, $this->get('channels', []));
    }
}

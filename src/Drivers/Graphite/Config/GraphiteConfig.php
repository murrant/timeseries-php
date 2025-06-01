<?php

namespace TimeSeriesPhp\Drivers\Graphite\Config;

use TimeSeriesPhp\Core\Attributes\Config;
use TimeSeriesPhp\Core\Config\AbstractConfig;
use TimeSeriesPhp\Drivers\Graphite\GraphiteDriver;
use TimeSeriesPhp\Exceptions\Config\ConfigurationException;

#[Config('graphite', GraphiteDriver::class)]
class GraphiteConfig extends AbstractConfig
{
    protected array $defaults = [
        'host' => 'localhost',
        'port' => 2003,
        'protocol' => 'tcp',
        'timeout' => 30,
        'prefix' => '',
        'batch_size' => 500,
        'web_host' => 'localhost',
        'web_port' => 8080,
        'web_protocol' => 'http',
        'web_path' => '/render',
    ];

    protected array $required = ['host', 'port'];

    /**
     * @throws ConfigurationException
     */
    public function __construct(array $config = [])
    {
        $this->addValidator('host', fn ($host) => is_string($host) && ! empty($host));
        $this->addValidator('port', fn ($port) => is_int($port) && $port > 0);
        $this->addValidator('protocol', fn ($protocol) => in_array($protocol, ['tcp', 'udp']));
        $this->addValidator('timeout', fn ($timeout) => is_int($timeout) && $timeout > 0);
        $this->addValidator('prefix', fn ($prefix) => is_string($prefix));
        $this->addValidator('batch_size', fn ($size) => is_int($size) && $size > 0);
        $this->addValidator('web_host', fn ($host) => is_string($host) && ! empty($host));
        $this->addValidator('web_port', fn ($port) => is_int($port) && $port > 0);
        $this->addValidator('web_protocol', fn ($protocol) => in_array($protocol, ['http', 'https']));
        $this->addValidator('web_path', fn ($path) => is_string($path) && ! empty($path));

        parent::__construct($config);
    }

    /**
     * Get the connection string for the Graphite server
     */
    public function getConnectionString(): string
    {
        return $this->getString('host').':'.$this->getInt('port');
    }

    /**
     * Get the web URL for the Graphite server
     */
    public function getWebUrl(): string
    {
        return $this->getString('web_protocol').'://'.$this->getString('web_host').':'.$this->getInt('web_port').$this->getString('web_path');
    }
}

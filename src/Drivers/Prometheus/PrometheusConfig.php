<?php

namespace TimeSeriesPhp\Drivers\Prometheus;

use TimeSeriesPhp\Config\AbstractDriverConfig;
use TimeSeriesPhp\Exceptions\ConfigurationException;

class PrometheusConfig extends AbstractDriverConfig
{
    protected string $driverName = 'prometheus';

    protected array $defaults = [
        'url' => 'http://localhost:9090',
        'timeout' => 30,
        'verify_ssl' => true,
        'debug' => false,
    ];

    protected array $required = ['url'];

    /**
     * @throws ConfigurationException
     */
    public function __construct(array $config = [])
    {
        $this->addValidator('url', fn ($url) => is_string($url) && ! empty($url));
        $this->addValidator('timeout', fn ($timeout) => is_int($timeout) && $timeout > 0);
        $this->addValidator('verify_ssl', fn ($verify) => is_bool($verify));
        $this->addValidator('debug', fn ($debug) => is_bool($debug));

        parent::__construct($config);
    }

    public function getClientConfig(): array
    {
        return [
            'url' => $this->get('url'),
            'timeout' => $this->get('timeout'),
            'verify_ssl' => $this->get('verify_ssl'),
            'debug' => $this->get('debug'),
        ];
    }
}

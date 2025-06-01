<?php

namespace TimeSeriesPhp\Drivers\Prometheus\Config;

use TimeSeriesPhp\Core\Attributes\Config;
use TimeSeriesPhp\Core\Config\AbstractConfig;
use TimeSeriesPhp\Drivers\Prometheus\PrometheusDriver;
use TimeSeriesPhp\Exceptions\Config\ConfigurationException;

#[Config('prometheus', PrometheusDriver::class)]
class PrometheusConfig extends AbstractConfig
{
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
}

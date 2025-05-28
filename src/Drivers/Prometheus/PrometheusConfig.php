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

    /**
     * @return array{'url': string, 'timeout': int, 'verify_ssl': bool, 'debug': bool}
     * @throws ConfigurationException
     */
    public function getClientConfig(): array
    {
        return [
            'url' => $this->getString('url'),
            'timeout' => $this->getInt('timeout'),
            'verify_ssl' => $this->getBool('verify_ssl'),
            'debug' => $this->getBool('debug'),
        ];
    }
}

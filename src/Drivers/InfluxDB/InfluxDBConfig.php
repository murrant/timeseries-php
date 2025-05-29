<?php

namespace TimeSeriesPhp\Drivers\InfluxDB;

use InfluxDB2\Model\WritePrecision;
use TimeSeriesPhp\Config\AbstractDriverConfig;
use TimeSeriesPhp\Exceptions\ConfigurationException;

class InfluxDBConfig extends AbstractDriverConfig
{
    protected string $driverName = 'influxdb';

    protected array $defaults = [
        'url' => 'http://localhost:8086',
        'token' => '',
        'org' => '',
        'bucket' => '',
        'timeout' => 30,
        'verify_ssl' => true,
        'debug' => false,
        'precision' => WritePrecision::NS,
    ];

    protected array $required = ['url', 'token', 'org', 'bucket'];

    /**
     * @throws ConfigurationException
     */
    public function __construct(array $config = [])
    {
        $this->addValidator('url', fn ($url) => is_string($url) && ! empty($url));
        $this->addValidator('token', fn ($token) => is_string($token) && ! empty($token));
        $this->addValidator('org', fn ($org) => is_string($org) && ! empty($org));
        $this->addValidator('bucket', fn ($bucket) => is_string($bucket) && ! empty($bucket));
        $this->addValidator('timeout', fn ($timeout) => is_int($timeout) && $timeout > 0);
        $this->addValidator('verify_ssl', fn ($verify) => is_bool($verify));
        $this->addValidator('debug', fn ($debug) => is_bool($debug));
        $this->addValidator('precision', fn ($precision) => in_array($precision, WritePrecision::getAllowableEnumValues()));

        parent::__construct($config);
    }

    /**
     * @return array<string, mixed>
     */
    public function getClientConfig(): array
    {
        return [
            'url' => $this->getString('url'),
            'token' => $this->getString('token'),
            'bucket' => $this->getString('bucket'),
            'org' => $this->getString('org'),
            'precision' => $this->getString('precision'),
            'timeout' => $this->getInt('timeout'),
            'verifySSL' => $this->getBool('verify_ssl'),
            'debug' => $this->getBool('debug'),
        ];
    }
}

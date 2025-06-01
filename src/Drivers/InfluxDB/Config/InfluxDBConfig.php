<?php

namespace TimeSeriesPhp\Drivers\InfluxDB\Config;

use InfluxDB2\Model\WritePrecision;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use TimeSeriesPhp\Core\Attributes\Config;
use TimeSeriesPhp\Core\Driver\AbstractDriverConfiguration;
use TimeSeriesPhp\Drivers\InfluxDB\InfluxDBDriver;

/**
 * Configuration for the InfluxDB driver
 */
#[Config('influxdb', InfluxDBDriver::class)]
class InfluxDBConfig extends AbstractDriverConfiguration
{
    /**
     * @param  string  $url  The InfluxDB server URL
     * @param  string  $token  The InfluxDB API token
     * @param  string  $org  The InfluxDB organization
     * @param  string  $bucket  The InfluxDB bucket
     * @param  int  $timeout  Connection timeout in seconds
     * @param  bool  $verify_ssl  Whether to verify SSL certificates
     * @param  bool  $debug  Enable debug mode
     * @param  string  $precision  The timestamp precision
     */
    public function __construct(
        public readonly string $url = 'http://localhost:8086',
        public readonly string $token = '',
        public readonly string $org = '',
        public readonly string $bucket = '',
        public readonly int $timeout = 30,
        public readonly bool $verify_ssl = true,
        public readonly bool $debug = false,
        public readonly string $precision = WritePrecision::NS,
    ) {}

    /**
     * Configure the schema for this driver
     *
     * @param  ArrayNodeDefinition  $rootNode  The root node
     */
    protected function configureSchema(ArrayNodeDefinition $rootNode): void
    {
        $rootNode
            ->children()
            ->scalarNode('url')
            ->info('The InfluxDB server URL')
            ->isRequired()
            ->cannotBeEmpty()
            ->end()
            ->scalarNode('token')
            ->info('The InfluxDB API token')
            ->isRequired()
            ->cannotBeEmpty()
            ->end()
            ->scalarNode('org')
            ->info('The InfluxDB organization')
            ->isRequired()
            ->cannotBeEmpty()
            ->end()
            ->scalarNode('bucket')
            ->info('The InfluxDB bucket')
            ->isRequired()
            ->cannotBeEmpty()
            ->end()
            ->integerNode('timeout')
            ->info('Connection timeout in seconds')
            ->defaultValue(30)
            ->min(1)
            ->end()
            ->booleanNode('verify_ssl')
            ->info('Whether to verify SSL certificates')
            ->defaultTrue()
            ->end()
            ->booleanNode('debug')
            ->info('Enable debug mode')
            ->defaultFalse()
            ->end()
            ->enumNode('precision')
            ->info('The timestamp precision')
            ->values(WritePrecision::getAllowableEnumValues())
            ->defaultValue(WritePrecision::NS)
            ->end()
            ->end();
    }

    /**
     * @return array<string, mixed>
     */
    public function getClientConfig(): array
    {
        return [
            'url' => $this->url,
            'token' => $this->token,
            'bucket' => $this->bucket,
            'org' => $this->org,
            'precision' => $this->precision,
            'timeout' => $this->timeout,
            'verifySSL' => $this->verify_ssl,
            'debug' => $this->debug,
        ];
    }
}

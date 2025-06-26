<?php

namespace TimeSeriesPhp\Drivers\InfluxDB;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use TimeSeriesPhp\Core\Attributes\Config;
use TimeSeriesPhp\Core\Driver\AbstractDriverConfiguration;
use TimeSeriesPhp\Core\Enum\TimePrecision;

/**
 * Configuration for the InfluxDB driver
 */
#[Config('influxdb', InfluxDBDriver::class)]
class InfluxDBConfig extends AbstractDriverConfiguration
{
    public function __construct(
        public readonly string $url = 'http://localhost:8086',
        public readonly string $token = '',
        public readonly string $org = '',
        public readonly string $bucket = '',
        public readonly int $timeout = 30,
        public readonly bool $verify_ssl = true,
        public readonly bool $debug = false,
        public readonly string $precision = TimePrecision::NS->value,
        public readonly string $connection_type = 'http',
        public readonly int $udp_port = 8089,
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
            ->values(TimePrecision::values())
            ->defaultValue(TimePrecision::NS->value)
            ->end()
            ->enumNode('connection_type')
            ->info('The connection type')
            ->values(['http', 'udp'])
            ->defaultValue('http')
            ->end()
            ->integerNode('udp_port')
            ->info('The port for 1.x UDP write socket')
            ->defaultValue(8089)
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

<?php

namespace TimeseriesPhp\Driver\RRD;

use TimeseriesPhp\Core\Contracts\DriverConfig;
use TimeseriesPhp\Core\Metrics\RetentionPolicy;
use TimeseriesPhp\Driver\RRD\Exceptions\RrdConfigException;

final readonly class RrdConfig implements DriverConfig
{
    /**
     * @param  RetentionPolicy[]  $defaultRetentionPolicies
     *
     * @throws RrdConfigException
     */
    public function __construct(
        public string $dir,
        public ?string $rrdtool_exec = null,
        public ?string $rrdcached = null,
        public int $process_timeout = 300,
        public array $defaultRetentionPolicies = [],
    ) {
        $this->validate();
    }

    /**
     * @param  DriverConfig|array<string, mixed>  $config
     *
     * @throws RrdConfigException
     */
    public static function make(DriverConfig|array $config = []): self
    {
        if (is_array($config)) {
            return self::fromArray($config);
        }

        if (! $config instanceof self) {
            throw new RrdConfigException('Invalid config type provided');
        }

        return $config;
    }

    /**
     * @param  array<string, mixed>  $config
     *
     * @throws RrdConfigException
     */
    public static function fromArray(array $config): self
    {
        return new self(
            dir: (string) $config['dir'],
            rrdtool_exec: isset($config['rrdtool_exec']) ? (string) $config['rrdtool_exec'] : 'rrdtool',
            rrdcached: isset($config['rrdcached']) ? (string) $config['rrdcached'] : null,
            defaultRetentionPolicies: array_map(
                RetentionPolicy::fromArray(...),
                $config['default_retention_policies'] ?? []
            ),
        );
    }

    /**
     * @throws RrdConfigException
     */
    private function validate(): void
    {
        if (! $this->rrdcached && ! file_exists($this->dir)) {
            throw new RrdConfigException("RRD directory does not exist: $this->dir");
        }
    }
}

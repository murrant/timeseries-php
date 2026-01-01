<?php

namespace TimeseriesPhp\Driver\RRD;

use TimeseriesPhp\Core\Contracts\TsdbConfig;
use TimeseriesPhp\Core\Metrics\RetentionPolicy;
use TimeseriesPhp\Driver\RRD\Exceptions\RrdConfigException;

final readonly class RrdConfig implements TsdbConfig
{
    /**
     * @param  RetentionPolicy[]  $defaultRetentionPolicies
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
     * @param  array<string, mixed>  $config
     */
    public static function fromArray(array $config): TsdbConfig
    {
        return new self(
            dir: $config['dir'],
            rrdtool_exec: $config['rrdtool_exec'] ?? 'rrdtool',
            rrdcached: $config['rrdcached'] ?? null,
            defaultRetentionPolicies: array_map(
                RetentionPolicy::fromArray(...),
                $config['default_retention_policies'] ?? []
            ),
        );
    }

    private function validate(): void
    {
        if (! file_exists($this->dir)) {
            throw new RrdConfigException("RRD directory does not exist: {$this->dir}");
        }
    }
}

<?php

namespace TimeseriesPhp\Driver\RRD\Traits;

use TimeseriesPhp\Core\Enum\Aggregation;
use TimeseriesPhp\Core\Enum\MetricType;
use TimeseriesPhp\Core\Metrics\RetentionPolicy;
use TimeseriesPhp\Driver\RRD\Exceptions\RrdException;
use TimeseriesPhp\Driver\RRD\RrdCommand;

trait RrdCommandBuilder
{
    /**
     * @param  array<string, MetricType>  $ds
     * @param  RetentionPolicy[]  $retentionPolicies
     *
     * @throws RrdException
     */
    private function buildCreateCommand(string $path, array $ds, array $retentionPolicies): RrdCommand
    {
        $heartbeat = null;
        $rras = [];
        foreach ($retentionPolicies as $policy) {
            $steps = $policy->retention;
            $heartbeat = min($heartbeat, $policy->resolution);
            $rows = $policy->getPointCount();
            $cf = match ($policy->aggregator) {
                Aggregation::Average => 'AVERAGE',
                Aggregation::Maximum => 'MAX',
                Aggregation::Minimum => 'MIN',
            };

            $rras[] = sprintf('RRA:%s:0.5:%d:%d', $cf, $steps, $rows);
        }

        $datasets = [];
        foreach ($ds as $name => $type) {
            $typeName = match ($type) {
                MetricType::COUNTER => 'COUNTER',
                MetricType::GAUGE => 'GAUGE',
                default => throw new RrdException('Unsupported metric type: '.$type->name),
            };
            // DS:ds-name:DST:heartbeat:min:max
            // Heartbeat is usually 2x the resolution to allow for small gaps in data
            $datasets[] = sprintf('DS:%s:%s:%d:U:U', $name, $typeName, ($heartbeat ?? 300) * 2); // TODO min/max needed? probably
        }

        return new RrdCommand('create', [], [$path, ...$datasets, ...$rras]);
    }

    private function buildListCommand(string $directory, bool $recursive = false): RrdCommand
    {
        $params = $recursive ? ['--recursive'] : [];

        if ($this->config->rrdcached) {
            $directory = str_replace($this->config->dir, '/', $directory);
        }

        return new RrdCommand('list', $params, [$directory]);
    }

    /**
     * @param  array<string|int, int|float|null>  $data
     */
    private function buildUpdateCommand(string $path, array $data, ?int $timestamp = null): RrdCommand
    {
        $update = implode(':', [$timestamp ?? time(), ...$data]);

        return new RrdCommand('update', [], [$path, $update]);
    }
}

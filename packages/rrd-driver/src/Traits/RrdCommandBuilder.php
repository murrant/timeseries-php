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
        $step = min(array_map(fn ($policy) => $policy->resolution, $retentionPolicies));

        $rras = [];
        foreach ($retentionPolicies as $policy) {
            $stepsPerRow = $policy->resolution / $step;
            $rows = $policy->getPointCount();
            $cf = match ($policy->aggregator) {
                Aggregation::Average => 'AVERAGE',
                Aggregation::Maximum => 'MAX',
                Aggregation::Minimum => 'MIN',
            };

            $rras[] = sprintf('RRA:%s:0.5:%d:%d', $cf, $stepsPerRow, $rows);
        }

        $datasets = [];
        foreach ($ds as $name => $type) {
            $typeName = match ($type) {
                MetricType::COUNTER => 'COUNTER',
                MetricType::GAUGE => 'GAUGE',
                default => throw new RrdException('Unsupported metric type: '.$type->name),
            };
            $datasets[] = sprintf('DS:%s:%s:%d:U:U', $name, $typeName, $step * 2);
        }

        return new RrdCommand('create', ['--step' => $step], [$path, ...$datasets, ...$rras]);
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
        $update = implode(':', [$timestamp ?? 'N', ...$data]);

        return new RrdCommand('update', [], [$path, $update]);
    }

    private function buildInfoCommand(string $path): RrdCommand
    {
        return new RrdCommand('info', [], [$path]);
    }
}

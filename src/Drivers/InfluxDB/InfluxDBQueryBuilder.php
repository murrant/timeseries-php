<?php

namespace TimeSeriesPhp\Drivers\InfluxDB;

use TimeSeriesPhp\Core\Query;
use TimeSeriesPhp\Core\QueryBuilderContract;
use TimeSeriesPhp\Core\RawQuery;
use TimeSeriesPhp\Core\RawQueryContract;

class InfluxDBQueryBuilder implements QueryBuilderContract
{
    private string $bucket;

    public function __construct(string $bucket)
    {
        $this->bucket = $bucket;
    }

    public function build(Query $query): RawQueryContract
    {
        $fields = $query->getFields();
        $measurement = $query->getMeasurement();

        // Build Flux query (InfluxDB 2.x uses Flux, not InfluxQL)
        $fluxQuery = "from(bucket: \"{$this->bucket}\")\n";

        // Add time range
        if ($query->getStartTime() && $query->getEndTime()) {
            $start = $query->getStartTime()->format('c');
            $stop = $query->getEndTime()->format('c');
            $fluxQuery .= "  |> range(start: {$start}, stop: {$stop})\n";
        } elseif ($query->getStartTime()) {
            $start = $query->getStartTime()->format('c');
            $fluxQuery .= "  |> range(start: {$start})\n";
        } else {
            // Default to last hour if no time range specified
            $fluxQuery .= "  |> range(start: -1h)\n";
        }

        // Filter by measurement
        if ($measurement) {
            $fluxQuery .= "  |> filter(fn: (r) => r._measurement == \"{$measurement}\")\n";
        }

        // Add tag filters
        foreach ($query->getTags() as $tag => $value) {
            $fluxQuery .= "  |> filter(fn: (r) => r.{$tag} == \"{$value}\")\n";
        }

        // Filter by fields if specified
        if (!empty($fields) && !in_array('*', $fields)) {
            $fieldConditions = array_map(function($field) {
                return "r._field == \"{$field}\"";
            }, $fields);
            $fieldCondition = implode(' or ', $fieldConditions);
            $fluxQuery .= "  |> filter(fn: (r) => {$fieldCondition})\n";
        }

        // Add aggregation with windowing if specified
        if ($query->getAggregation()) {
            $aggregation = strtolower($query->getAggregation());
            $interval = $query->getInterval();

            if ($interval) {
                // Convert interval to Flux duration format
                $duration = $this->convertIntervalToDuration($interval);
                $fluxQuery .= "  |> aggregateWindow(every: {$duration}, fn: {$aggregation}, createEmpty: false)\n";
            } else {
                // Apply aggregation without windowing
                switch ($aggregation) {
                    case 'mean':
                    case 'avg':
                        $fluxQuery .= "  |> mean()\n";
                        break;
                    case 'sum':
                        $fluxQuery .= "  |> sum()\n";
                        break;
                    case 'count':
                        $fluxQuery .= "  |> count()\n";
                        break;
                    case 'min':
                        $fluxQuery .= "  |> min()\n";
                        break;
                    case 'max':
                        $fluxQuery .= "  |> max()\n";
                        break;
                    case 'first':
                        $fluxQuery .= "  |> first()\n";
                        break;
                    case 'last':
                        $fluxQuery .= "  |> last()\n";
                        break;
                    case 'stddev':
                        $fluxQuery .= "  |> stddev()\n";
                        break;
                    default:
                        // For custom or unsupported aggregations, try to use them directly
                        $fluxQuery .= "  |> {$aggregation}()\n";
                }
            }
        }

        // Add grouping
        if (!empty($query->getGroupBy())) {
            $groupCols = array_map(function($col) {
                return "\"{$col}\"";
            }, $query->getGroupBy());
            $fluxQuery .= "  |> group(columns: [" . implode(', ', $groupCols) . "])\n";
        }

        // Add ordering (sort)
        if (!empty($query->getOrderBy())) {
            foreach ($query->getOrderBy() as $field => $direction) {
                $desc = strtoupper($direction) === 'DESC' ? 'true' : 'false';
                $fluxQuery .= "  |> sort(columns: [\"{$field}\"], desc: {$desc})\n";
            }
        }

        // Add limit
        if ($query->getLimit()) {
            $fluxQuery .= "  |> limit(n: {$query->getLimit()})\n";
        }

        return new RawQuery($fluxQuery);
    }

    private function convertIntervalToDuration(string $interval): string
    {
        // Simple conversion from common interval formats to Flux duration
        // This is a simplified implementation - extend as needed
        if (preg_match('/^(\d+)([smhdw])$/', $interval, $matches)) {
            $amount = $matches[1];
            $unit = $matches[2];

            switch ($unit) {
                case 's': return "{$amount}s";
                case 'm': return "{$amount}m";
                case 'h': return "{$amount}h";
                case 'd': return "{$amount}d";
                case 'w': return "{$amount}w";
            }
        }

        // Default fallback
        return $interval;
    }
}

<?php

namespace TimeSeriesPhp\Drivers\Prometheus;

use TimeSeriesPhp\Core\Query;
use TimeSeriesPhp\Core\QueryBuilderContract;
use TimeSeriesPhp\Core\RawQuery;
use TimeSeriesPhp\Core\RawQueryContract;

class PrometheusQueryBuilder implements QueryBuilderContract
{
    public function build(Query $query): RawQueryContract
    {
        // Prometheus uses PromQL
        $metric = $query->getMeasurement();
        $promqlQuery = '';

        // Build label selectors from conditions
        $labelSelectors = [];
        foreach ($query->getConditions() as $condition) {
            $field = $condition['field'];
            $operator = $condition['operator'];
            $value = $condition['value'];

            // Convert operators to Prometheus format
            switch ($operator) {
                case '=':
                case '==':
                    if (! is_array($value)) {
                        $labelSelectors[] = "{$field}=\"{$value}\"";
                    }
                    break;
                case '!=':
                case '<>':
                    $labelSelectors[] = "{$field}!=\"{$value}\"";
                    break;
                case 'REGEX':
                    $labelSelectors[] = "{$field}=~\"{$value}\"";
                    break;
                case 'NOT REGEX':
                    $labelSelectors[] = "{$field}!~\"{$value}\"";
                    break;
                case 'IN':
                    if (is_array($value) && count($value) > 0) {
                        $values = array_map(function ($v) {
                            return "\"$v\"";
                        }, $value);
                        $labelSelectors[] = "{$field}=~\"^(".implode('|', $value).')$"';
                    }
                    break;
                case 'NOT IN':
                    if (is_array($value) && count($value) > 0) {
                        $values = array_map(function ($v) {
                            return "\"$v\"";
                        }, $value);
                        $labelSelectors[] = "{$field}!~\"^(".implode('|', $value).')$"';
                    }
                    break;
                    // Other operators like >, <, >=, <= are not directly supported in label selectors
                    // They would need to be handled in post-processing or with specific functions
            }
        }

        // Build the metric selector part
        $labelSelectorStr = empty($labelSelectors) ? '' : '{'.implode(',', $labelSelectors).'}';
        $metricSelector = $metric.$labelSelectorStr;

        // Handle time range
        $timeRange = '';
        if ($query->getStartTime() && $query->getEndTime()) {
            // In PromQL, time ranges are typically handled by the HTTP API parameters, not in the query itself
            // But we can add a comment to indicate the intended time range
            $timeRange = " # time range: {$query->getStartTime()->format('c')} to {$query->getEndTime()->format('c')}";
        } elseif ($query->getRelativeTime()) {
            $timeRange = ' # relative time: '.$this->formatDateInterval($query->getRelativeTime());
        }

        // Handle aggregations
        if (! empty($query->getAggregations())) {
            $aggregations = $query->getAggregations();
            $agg = $aggregations[0]; // Use the first aggregation (Prometheus typically supports one at a time)
            $function = strtolower($agg['function']);

            // Map common aggregation functions to Prometheus functions
            switch (substr($function, 0, strpos($function, '_') ?: null)) {
                case 'avg':
                case 'mean':
                    $promqlQuery = "avg({$metricSelector})";
                    break;
                case 'sum':
                    $promqlQuery = "sum({$metricSelector})";
                    break;
                case 'min':
                    $promqlQuery = "min({$metricSelector})";
                    break;
                case 'max':
                    $promqlQuery = "max({$metricSelector})";
                    break;
                case 'count':
                    $promqlQuery = "count({$metricSelector})";
                    break;
                case 'stddev':
                    $promqlQuery = "stddev({$metricSelector})";
                    break;
                case 'percentile':
                    // Extract percentile value from the function name (e.g., PERCENTILE_95 -> 0.95)
                    if (str_starts_with($function, 'percentile_')) {
                        $percentileValue = substr($function, 11);
                        if ($percentileValue !== '' && is_numeric($percentileValue)) {
                            $percentile = (float) $percentileValue / 100;
                            $promqlQuery = "quantile({$percentile}, {$metricSelector})";
                        } else {
                            // Default to 50th percentile if no valid value is provided
                            $promqlQuery = "quantile(0.5, {$metricSelector})";
                        }
                    } else {
                        // Default to the original function name
                        $promqlQuery = "{$function}({$metricSelector})";
                    }
                    break;
                default:
                    // Use the function name as-is
                    $promqlQuery = "{$function}({$metricSelector})";
            }

            // Handle group by (in Prometheus, this is done with 'by' clause)
            if (! empty($query->getGroupBy())) {
                $groupBy = implode(',', $query->getGroupBy());
                $promqlQuery = str_replace(')', " by ({$groupBy}))", $promqlQuery);
            }
        } else {
            // No aggregation, just use the metric selector
            $promqlQuery = $metricSelector;
        }

        // Handle rate() for counter metrics if interval is specified
        if ($query->getInterval()) {
            // In Prometheus, rate() is commonly used with counters over a time interval
            $interval = $this->convertIntervalToPromQL($query->getInterval());
            $promqlQuery = "rate({$promqlQuery}[{$interval}])";
        }

        // Handle math expressions
        if (! empty($query->getMathExpressions())) {
            $mathExpr = $query->getMathExpressions()[0]; // Use the first math expression
            $expression = $mathExpr['expression'];

            // In PromQL, we can apply mathematical operations directly
            // This is a simplified approach - complex expressions might need more handling
            $promqlQuery = "({$promqlQuery}) {$expression}";
        }

        // Handle limit (not directly supported in PromQL, but can be added as a comment)
        if ($query->getLimit() !== null) {
            $promqlQuery .= " # limit: {$query->getLimit()}";
        }

        // Add time range comment if applicable
        $promqlQuery .= $timeRange;

        return new RawQuery($promqlQuery);
    }

    private function formatDateInterval(\DateInterval $interval): string
    {
        // Convert DateInterval to a human-readable string for comments
        $parts = [];

        if ($interval->y > 0) {
            $parts[] = $interval->y.'y';
        }
        if ($interval->m > 0) {
            $parts[] = $interval->m.'mo';
        }
        if ($interval->d > 0) {
            $parts[] = $interval->d.'d';
        }
        if ($interval->h > 0) {
            $parts[] = $interval->h.'h';
        }
        if ($interval->i > 0) {
            $parts[] = $interval->i.'m';
        }
        if ($interval->s > 0) {
            $parts[] = $interval->s.'s';
        }

        return implode(' ', $parts) ?: '0s';
    }

    private function convertIntervalToPromQL(string $interval): string
    {
        // Convert interval string to PromQL duration format
        // Examples: '1h' -> '1h', '30m' -> '30m', '5s' -> '5s'
        if (preg_match('/^(\d+)([smhdwy])$/', $interval, $matches)) {
            $amount = $matches[1];
            $unit = $matches[2];

            switch ($unit) {
                case 's': return "{$amount}s";
                case 'm': return "{$amount}m";
                case 'h': return "{$amount}h";
                case 'd': return "{$amount}d";
                case 'w': return "{$amount}w";
                case 'y': return "{$amount}y";
            }
        }

        // Default fallback
        return $interval;
    }
}

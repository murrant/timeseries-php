<?php

namespace TimeSeriesPhp\Drivers\Prometheus\Query;

use TimeSeriesPhp\Contracts\Query\ComparisonOperator;
use TimeSeriesPhp\Contracts\Query\QueryBuilderInterface;
use TimeSeriesPhp\Contracts\Query\RawQueryInterface;
use TimeSeriesPhp\Core\Query\Query;
use TimeSeriesPhp\Core\Query\RawQuery;

class PrometheusQueryBuilder implements QueryBuilderInterface
{
    public function build(Query $query): RawQueryInterface
    {
        // Prometheus uses PromQL
        $metric = $query->getMeasurement();
        $promqlQuery = '';

        // Build label selectors from conditions
        $labelSelectors = [];
        foreach ($query->getConditions() as $condition) {
            $field = $condition->getField();
            $operator = $condition->getOperator();
            $value = $condition->getScalarValue();

            // Convert operators to Prometheus format
            if ($operator !== ComparisonOperator::BETWEEN) {
                $labelSelectors[] = match ($operator) {
                    ComparisonOperator::EQUALS, ComparisonOperator::SAME => "{$field}=\"{$value}\"",
                    ComparisonOperator::NOT_EQUALS, ComparisonOperator::NOT_EQUALS_ALT => "{$field}!=\"{$value}\"",
                    ComparisonOperator::REGEX => "{$field}=~\"{$value}\"",
                    ComparisonOperator::IN => (function () use ($field, $condition) {
                        $values = $condition->getValues();

                        return "{$field}=~\"^(".implode('|', $values).')$"';
                    })(),
                    ComparisonOperator::NOT_IN => (function () use ($field, $condition) {
                        $values = $condition->getValues();

                        return "{$field}!~\"^(".implode('|', $values).')$"';
                    })(),
                    // Other operators like >, <, >=, <= are not directly supported in label selectors
                    // They would need to be handled in post-processing or with specific functions
                    default => "{$field}=\"{$value}\"", // Default to equality
                };
            }
        }

        // Build the metric selector part
        $labelSelectorStr = empty($labelSelectors) ? '' : '{'.implode(',', $labelSelectors).'}';
        $metricSelector = $metric.$labelSelectorStr;

        // Handle time range
        $timeRange = match (true) {
            $query->getStartTime() && $query->getEndTime() => " # time range: {$query->getStartTime()->format('c')} to {$query->getEndTime()->format('c')}",
            $query->getRelativeTime() !== null => ' # relative time: '.$this->formatDateInterval($query->getRelativeTime()),
            default => '',
        };

        // Handle aggregations
        if (! empty($query->getAggregations())) {
            $aggregations = $query->getAggregations();
            $agg = $aggregations[0]; // Use the first aggregation (Prometheus typically supports one at a time)
            $function = strtolower($agg['function']);

            // Map common aggregation functions to Prometheus functions
            $functionPrefix = str_contains($function, '_') ? explode('_', $function)[0] : $function;
            $promqlQuery = match ($functionPrefix) {
                'avg', 'mean' => "avg({$metricSelector})",
                'sum' => "sum({$metricSelector})",
                'min' => "min({$metricSelector})",
                'max' => "max({$metricSelector})",
                'count' => "count({$metricSelector})",
                'stddev' => "stddev({$metricSelector})",
                'percentile' => (function () use ($function, $metricSelector) {
                    // Extract percentile value from the function name (e.g., PERCENTILE_95 -> 0.95)
                    if (str_starts_with($function, 'percentile_')) {
                        $percentileValue = substr($function, 11);
                        if ($percentileValue !== '' && is_numeric($percentileValue)) {
                            $percentile = (float) $percentileValue / 100;

                            return "quantile({$percentile}, {$metricSelector})";
                        } else {
                            // Default to 50th percentile if no valid value is provided
                            return "quantile(0.5, {$metricSelector})";
                        }
                    } else {
                        // Default to the original function name
                        return "{$function}({$metricSelector})";
                    }
                })(),
                default => "{$function}({$metricSelector})",
            };

            // Handle group by (in Prometheus, this is done with 'by' clause)
            if (! empty($query->getGroupBy())) {
                $groupBy = implode(',', $query->getGroupBy());
                // Extract the function name and arguments
                if (preg_match('/^(\w+)\((.*)\)$/', $promqlQuery, $matches)) {
                    $function = $matches[1];
                    $args = $matches[2];
                    // Reconstruct with the correct syntax: function by (labels) (args)
                    $promqlQuery = "{$function} by ({$groupBy}) ({$args})";
                }
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

            return match ($unit) {
                's' => "{$amount}s",
                'm' => "{$amount}m",
                'h' => "{$amount}h",
                'd' => "{$amount}d",
                'w' => "{$amount}w",
                'y' => "{$amount}y",
            };
        }

        // Default fallback
        return $interval;
    }
}

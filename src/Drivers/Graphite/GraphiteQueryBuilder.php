<?php

namespace TimeSeriesPhp\Drivers\Graphite;

use TimeSeriesPhp\Contracts\Query\ComparisonOperator;
use TimeSeriesPhp\Contracts\Query\QueryBuilderInterface;
use TimeSeriesPhp\Contracts\Query\RawQueryInterface;
use TimeSeriesPhp\Core\Query\Query;
use TimeSeriesPhp\Core\Query\RawQuery;

class GraphiteQueryBuilder implements QueryBuilderInterface
{
    public function __construct(
        private readonly string $prefix = ''
    ) {}

    public function build(Query $query): RawQueryInterface
    {
        $measurement = $query->getMeasurement();
        $fields = $query->getFields();
        $target = '';

        // Add prefix if set
        $metricPrefix = $this->prefix ? $this->prefix.'.' : '';

        // Build the base metric path
        $metricPath = $metricPrefix.$measurement;

        // Handle fields
        if (empty($fields) || in_array('*', $fields)) {
            $target = $metricPath.'.*';
        } else {
            // For multiple fields, we need to use group() function
            if (count($fields) > 1) {
                $fieldPaths = array_map(fn ($field) => '"'.$metricPath.'.'.$field.'"', $fields);
                $target = 'group('.implode(', ', $fieldPaths).')';
            } else {
                $target = $metricPath.'.'.$fields[0];
            }
        }

        // Handle time range
        $from = match (true) {
            $query->getStartTime() !== null => $query->getStartTime()->getTimestamp(),
            $query->getRelativeTime() !== null => '-'.$this->formatDateInterval($query->getRelativeTime()),
            default => '-1h',
        };
        $until = 'now';

        if ($query->getEndTime()) {
            $until = $query->getEndTime()->getTimestamp();
        }

        // Handle aggregations
        $aggregations = $query->getAggregations();

        $target = match (true) {
            count($aggregations) > 1 => (function () use ($target, $aggregations, $query) {
                // For multiple aggregations, create separate targets and group them
                $aggTargets = [];
                $baseTarget = $target; // Save the original target

                foreach ($aggregations as $agg) {
                    $function = strtolower($agg['function']);
                    $alias = $agg['alias'] ?? null;
                    $aggTarget = $baseTarget;

                    $aggTarget = match ($function) {
                        'mean', 'avg' => "averageSeries($aggTarget)",
                        'sum' => "sumSeries($aggTarget)",
                        'count' => "countSeries($aggTarget)",
                        'min' => "minSeries($aggTarget)",
                        'max' => "maxSeries($aggTarget)",
                        'stddev' => "stdev($aggTarget)",
                        'percentile' => (function () use ($function, $aggTarget) {
                            $percentile = substr($function, 11);

                            return "percentileOfSeries($aggTarget, $percentile)";
                        })(),
                        default => $aggTarget,
                    };

                    // Handle time grouping for each aggregation
                    if ($query->getInterval()) {
                        $interval = $this->convertIntervalToGraphite($query->getInterval());
                        $aggTarget = "summarize($aggTarget, \"$interval\", \"$function\")";
                    }

                    // Add alias if specified
                    if ($alias) {
                        $aggTarget = "alias($aggTarget, \"$alias\")";
                    }

                    $aggTargets[] = $aggTarget;
                }

                // Combine all aggregation targets using group()
                return 'group('.implode(',', $aggTargets).')';
            })(),
            count($aggregations) === 1 => (function () use ($target, $aggregations, $query) {
                // For a single aggregation, apply it directly
                $agg = $aggregations[0];
                $function = strtolower($agg['function']);
                $alias = $agg['alias'] ?? null;

                $result = match ($function) {
                    'mean', 'avg' => "averageSeries($target)",
                    'sum' => "sumSeries($target)",
                    'count' => "countSeries($target)",
                    'min' => "minSeries($target)",
                    'max' => "maxSeries($target)",
                    'stddev' => "stdev($target)",
                    'percentile' => (function () use ($function, $target) {
                        $percentile = substr($function, 11);

                        return "percentileOfSeries($target, $percentile)";
                    })(),
                    default => $target,
                };

                // Add alias if specified
                if ($alias) {
                    $result = "alias($result, \"$alias\")";
                }

                // Handle time grouping (summarize function in Graphite)
                if ($query->getInterval()) {
                    $interval = $this->convertIntervalToGraphite($query->getInterval());
                    $result = "summarize($result, \"$interval\", \"avg\")";
                }

                return $result;
            })(),
            $query->getInterval() => (function () use ($target, $query) {
                // No aggregations, just handle time grouping if needed
                $interval = $this->convertIntervalToGraphite($query->getInterval());

                return "summarize($target, \"$interval\", \"avg\")";
            })(),
            default => $target
        };

        // Handle conditions (where clauses)
        // Graphite doesn't have direct equivalents for SQL-like where clauses
        // We can use functions like exclude(), grep(), etc. for some filtering
        foreach ($query->getConditions() as $condition) {
            $field = $condition->getField();
            $operator = $condition->getOperator();
            $scalarValue = $condition->getScalarValue();

            // We can only handle certain types of conditions in Graphite
            $target = match ($operator) {
                ComparisonOperator::EQUALS, ComparisonOperator::SAME => str_replace('*', (string) $scalarValue, $target),
                ComparisonOperator::NOT_EQUALS, ComparisonOperator::NOT_EQUALS_ALT => "exclude($target, \"$scalarValue\")",
                ComparisonOperator::REGEX => "grep($target, \"$scalarValue\")",
                default => $target,
            };
        }

        // Handle limit
        if ($query->getLimit() !== null) {
            $limit = $query->getLimit();
            $target = "limit($target, $limit)";
        }

        // Handle ordering
        foreach ($query->getOrderBy() as $direction) {
            $desc = strtoupper($direction) === 'DESC';
            if ($desc) {
                $target = "sortByMaxima($target)";
            } else {
                $target = "sortByMinima($target)";
            }
        }

        // Build the final query parameters
        $params = [
            'target' => $target,
            'from' => $from,
            'until' => $until,
            'format' => 'json',
        ];

        // Convert to query string, but don't encode asterisks
        $queryString = http_build_query($params);
        $queryString = str_replace('%2A', '*', $queryString);

        return new RawQuery($queryString);
    }

    private function formatDateInterval(\DateInterval $interval): string
    {
        // Convert DateInterval to Graphite time format
        $duration = '';

        if ($interval->y > 0) {
            $duration .= $interval->y.'y';
        }
        if ($interval->m > 0) {
            $duration .= $interval->m.'mon';
        }
        if ($interval->d > 0) {
            $duration .= $interval->d.'d';
        }
        if ($interval->h > 0) {
            $duration .= $interval->h.'h';
        }
        if ($interval->i > 0) {
            $duration .= $interval->i.'min';
        }
        if ($interval->s > 0) {
            $duration .= $interval->s.'s';
        }

        return $duration ?: '0s';
    }

    private function convertIntervalToGraphite(string $interval): string
    {
        // Convert interval format to Graphite format
        // Examples: '1h' -> '1hour', '30m' -> '30minute', '5s' -> '5second'
        if (preg_match('/^(\d+)([smhdw])$/', $interval, $matches)) {
            $amount = $matches[1];
            $unit = $matches[2];

            return match ($unit) {
                's' => "{$amount}second",
                'm' => "{$amount}minute",
                'h' => "{$amount}hour",
                'd' => "{$amount}day",
                'w' => "{$amount}week",
            };
        }

        // Default fallback
        return $interval;
    }
}

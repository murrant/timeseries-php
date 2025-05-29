<?php

namespace TimeSeriesPhp\Drivers\Graphite;

use TimeSeriesPhp\Core\Query;
use TimeSeriesPhp\Core\QueryBuilderInterface;
use TimeSeriesPhp\Core\RawQuery;
use TimeSeriesPhp\Core\RawQueryInterface;

class GraphiteQueryBuilder implements QueryBuilderInterface
{
    private string $prefix;

    public function __construct(string $prefix = '')
    {
        $this->prefix = $prefix;
    }

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
                $fieldPaths = array_map(function ($field) use ($metricPath) {
                    return '"'.$metricPath.'.'.$field.'"';
                }, $fields);
                $target = 'group('.implode(', ', $fieldPaths).')';
            } else {
                $target = $metricPath.'.'.$fields[0];
            }
        }

        // Handle time range
        $from = '-1h';
        $until = 'now';

        if ($query->getStartTime()) {
            $from = $query->getStartTime()->getTimestamp();
        } elseif ($query->getRelativeTime()) {
            $from = '-'.$this->formatDateInterval($query->getRelativeTime());
        }

        if ($query->getEndTime()) {
            $until = $query->getEndTime()->getTimestamp();
        }

        // Handle aggregations
        $aggregations = $query->getAggregations();

        if (count($aggregations) > 1) {
            // For multiple aggregations, create separate targets and group them
            $aggTargets = [];
            $baseTarget = $target; // Save the original target

            foreach ($aggregations as $agg) {
                $function = strtolower($agg['function']);
                $alias = $agg['alias'] ?? null;
                $aggTarget = $baseTarget;

                switch ($function) {
                    case 'mean':
                    case 'avg':
                        $aggTarget = "averageSeries($aggTarget)";
                        break;
                    case 'sum':
                        $aggTarget = "sumSeries($aggTarget)";
                        break;
                    case 'count':
                        $aggTarget = "countSeries($aggTarget)";
                        break;
                    case 'min':
                        $aggTarget = "minSeries($aggTarget)";
                        break;
                    case 'max':
                        $aggTarget = "maxSeries($aggTarget)";
                        break;
                    case 'stddev':
                        $aggTarget = "stdev($aggTarget)";
                        break;
                    case 'percentile':
                        $percentile = substr($function, 11);
                        $aggTarget = "percentileOfSeries($aggTarget, $percentile)";
                        break;
                }

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
            $target = 'group('.implode(',', $aggTargets).')';
        } elseif (count($aggregations) === 1) {
            // For a single aggregation, apply it directly
            $agg = $aggregations[0];
            $function = strtolower($agg['function']);
            $alias = $agg['alias'] ?? null;

            switch ($function) {
                case 'mean':
                case 'avg':
                    $target = "averageSeries($target)";
                    break;
                case 'sum':
                    $target = "sumSeries($target)";
                    break;
                case 'count':
                    $target = "countSeries($target)";
                    break;
                case 'min':
                    $target = "minSeries($target)";
                    break;
                case 'max':
                    $target = "maxSeries($target)";
                    break;
                case 'stddev':
                    $target = "stdev($target)";
                    break;
                case 'percentile':
                    $percentile = substr($function, 11);
                    $target = "percentileOfSeries($target, $percentile)";
                    break;
            }

            // Add alias if specified
            if ($alias) {
                $target = "alias($target, \"$alias\")";
            }

            // Handle time grouping (summarize function in Graphite)
            if ($query->getInterval()) {
                $interval = $this->convertIntervalToGraphite($query->getInterval());
                $target = "summarize($target, \"$interval\", \"avg\")";
            }
        } else {
            // No aggregations, just handle time grouping if needed
            if ($query->getInterval()) {
                $interval = $this->convertIntervalToGraphite($query->getInterval());
                $target = "summarize($target, \"$interval\", \"avg\")";
            }
        }

        // Handle conditions (where clauses)
        // Graphite doesn't have direct equivalents for SQL-like where clauses
        // We can use functions like exclude(), grep(), etc. for some filtering
        foreach ($query->getConditions() as $condition) {
            $field = $condition->getField();
            $operator = $condition->getOperator();
            $scalarValue = $condition->getScalarValue();

            // We can only handle certain types of conditions in Graphite
            if (($operator === '=' || $operator === '==')) {
                // For equality, we can use a more specific path
                $target = str_replace('*', (string) $scalarValue, $target);
            } elseif (($operator === '!=' || $operator === '<>')) {
                // For inequality, we can use exclude()
                $target = "exclude($target, \"$scalarValue\")";
            } elseif ($operator === 'REGEX') {
                // For regex, we can use grep()
                $target = "grep($target, \"$scalarValue\")";
            }
        }

        // Handle limit
        if ($query->getLimit() !== null) {
            $limit = $query->getLimit();
            $target = "limit($target, $limit)";
        }

        // Handle ordering
        foreach ($query->getOrderBy() as $field => $direction) {
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

            switch ($unit) {
                case 's': return "{$amount}second";
                case 'm': return "{$amount}minute";
                case 'h': return "{$amount}hour";
                case 'd': return "{$amount}day";
                case 'w': return "{$amount}week";
            }
        }

        // Default fallback
        return $interval;
    }
}

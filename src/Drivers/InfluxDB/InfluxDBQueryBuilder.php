<?php

namespace TimeSeriesPhp\Drivers\InfluxDB;

use TimeSeriesPhp\Core\Query;
use TimeSeriesPhp\Core\QueryBuilderInterface;
use TimeSeriesPhp\Core\RawQuery;
use TimeSeriesPhp\Core\RawQueryInterface;
use TimeSeriesPhp\Exceptions\QueryException;
use TimeSeriesPhp\Exceptions\RawQueryException;

class InfluxDBQueryBuilder implements QueryBuilderInterface
{
    public function __construct(
        private readonly string $bucket
    ) {}

    /**
     * @throws RawQueryException
     */
    public function build(Query $query): RawQueryInterface
    {
        $fields = $query->getFields();
        $measurement = $query->getMeasurement();

        // Build Flux query (InfluxDB 2.x uses Flux, not InfluxQL)
        $fluxQuery = "from(bucket: \"{$this->bucket}\")\n";

        // Add time range
        $fluxQuery .= match (true) {
            $query->getStartTime() && $query->getEndTime() => (function () use ($query) {
                $start = $query->getStartTime()->format('c');
                $stop = $query->getEndTime()->format('c');
                return "  |> range(start: {$start}, stop: {$stop})\n";
            })(),
            $query->getStartTime() => (function () use ($query) {
                $start = $query->getStartTime()->format('c');
                return "  |> range(start: {$start})\n";
            })(),
            $query->getRelativeTime() !== null => '  |> range(start: -'.$this->formatDateInterval($query->getRelativeTime()).")\n",
            default => "  |> range(start: -1h)\n", // Default to last hour if no time range specified
        };

        // Apply timezone if specified
        if ($query->getTimezone()) {
            $fluxQuery .= "  |> timeShift(duration: 0s, timeZone: \"{$query->getTimezone()}\")\n";
        }

        // Filter by measurement
        if ($measurement) {
            $fluxQuery .= "  |> filter(fn: (r) => r._measurement == \"{$measurement}\")\n";
        }

        // Add conditions (where clauses)
        foreach ($query->getConditions() as $condition) {
            $field = $condition->getField();
            $operator = $this->mapOperator($condition->getOperator());
            $type = $condition->getType();

            // Initialize $value to null
            $value = null;

            // Format value for operators that don't handle arrays differently
            if (! in_array($operator, ['IN', 'NOT IN', 'BETWEEN'])) {
                $value = $this->formatValue($condition->getValue());
            }

            // For InfluxDB, we need to handle different types of conditions
            $fluxQuery .= match (true) {
                $field === 'time' => match ($operator) {
                    '==' => "  |> filter(fn: (r) => r._time == {$value})\n",
                    '>' => "  |> filter(fn: (r) => r._time > {$value})\n",
                    '>=' => "  |> filter(fn: (r) => r._time >= {$value})\n",
                    '<' => "  |> filter(fn: (r) => r._time < {$value})\n",
                    '<=' => "  |> filter(fn: (r) => r._time <= {$value})\n",
                    '!=' => "  |> filter(fn: (r) => r._time != {$value})\n",
                    default => '',
                },
                $operator === 'IN' => (function () use ($field, $condition) {
                    $values = array_map(fn ($v) => $this->formatValue($v), $condition->getValues());
                    $valuesList = implode(', ', $values);
                    return "  |> filter(fn: (r) => contains(value: r[\"$field\"], set: [$valuesList]))\n";
                })(),
                $operator === 'NOT IN' => (function () use ($field, $condition) {
                    $values = $condition->getValues();
                    $conditions = [];
                    foreach ($values as $value) {
                        $formattedValue = $this->formatValue($value);
                        $conditions[] = "r[\"$field\"] != $formattedValue";
                    }
                    $conditionString = implode(' and ', $conditions);
                    return "  |> filter(fn: (r) => $conditionString)\n";
                })(),
                $operator === 'BETWEEN' && is_array($condition->getValue()) && count($condition->getValue()) === 2 => (function () use ($field, $condition) {
                    $min = $this->formatValue($condition->getValue()[0]);
                    $max = $this->formatValue($condition->getValue()[1]);
                    return "  |> filter(fn: (r) => r[\"$field\"] >= $min and r[\"$field\"] <= $max)\n";
                })(),
                $operator === 'REGEX' => (function () use ($field, $condition) {
                    $pattern = $condition->getScalarValue();
                    return "  |> filter(fn: (r) => r[\"$field\"] =~ /$pattern/)\n";
                })(),
                default => "  |> filter(fn: (r) => r[\"$field\"] $operator $value)\n", // Standard operators
            };
        }

        // Filter by fields if specified
        if (! empty($fields) && ! in_array('*', $fields)) {
            $fieldConditions = array_map(function ($field) {
                return "r._field == \"{$field}\"";
            }, $fields);
            $fieldCondition = implode(' or ', $fieldConditions);
            $fluxQuery .= "  |> filter(fn: (r) => {$fieldCondition})\n";
        }

        // Handle distinct
        if ($query->isDistinct()) {
            $fluxQuery .= "  |> distinct()\n";
        }

        // Add grouping
        if (! empty($query->getGroupBy())) {
            $groupCols = array_map(function ($col) {
                return "\"{$col}\"";
            }, $query->getGroupBy());
            $fluxQuery .= '  |> group(columns: ['.implode(', ', $groupCols)."])\n";
        }

        // Add time grouping
        if ($query->getInterval()) {
            $duration = $this->convertIntervalToDuration($query->getInterval());
            $fluxQuery .= "  |> window(every: {$duration})\n";
        }

        // Add aggregations
        $aggregations = $query->getAggregations();

        if (count($aggregations) > 1) {
            // For multiple aggregations, we need to duplicate the data for each aggregation
            $fieldCopies = [];
            $originalField = $aggregations[0]['field'] ?? 'value';

            // Create copies of the original field for each aggregation after the first one
            for ($i = 1; $i < count($aggregations); $i++) {
                $copyName = $originalField.'_copy'.$i;
                $fieldCopies[] = $copyName;
                $fluxQuery .= "  |> duplicate(column: \"{$originalField}\", as: \"{$copyName}\")\n";
            }

            // Apply each aggregation to its respective field
            foreach ($aggregations as $index => $agg) {
                $function = strtolower($agg['function']);
                $field = $index === 0 ? ($agg['field'] ?? 'value') : $fieldCopies[$index - 1];
                $alias = $agg['alias'] ?? null;

                // Apply aggregation
                $fluxQuery .= match ($function) {
                    'mean', 'avg' => "  |> mean(column: \"{$field}\")\n",
                    'sum' => "  |> sum(column: \"{$field}\")\n",
                    'count' => "  |> count(column: \"{$field}\")\n",
                    'min' => "  |> min(column: \"{$field}\")\n",
                    'max' => "  |> max(column: \"{$field}\")\n",
                    'first' => "  |> first(column: \"{$field}\")\n",
                    'last' => "  |> last(column: \"{$field}\")\n",
                    'stddev' => "  |> stddev(column: \"{$field}\")\n",
                    default => str_starts_with($function, 'percentile_')
                        ? '  |> quantile(q: '.substr($function, 11).", column: \"{$field}\")\n"
                        : "  |> {$function}(column: \"{$field}\")\n"
                };

                // Add alias if specified
                if ($alias) {
                    $fluxQuery .= "  |> rename(columns: {_value: \"{$alias}\"})\n";
                }
            }
        } else {
            // For a single aggregation, apply it directly
            foreach ($aggregations as $agg) {
                $function = strtolower($agg['function']);
                $field = $agg['field'] ?? '_value';
                $alias = $agg['alias'] ?? null;

                // Apply aggregation
                $fluxQuery .= match ($function) {
                    'mean', 'avg' => "  |> mean(column: \"{$field}\")\n",
                    'sum' => "  |> sum(column: \"{$field}\")\n",
                    'count' => "  |> count(column: \"{$field}\")\n",
                    'min' => "  |> min(column: \"{$field}\")\n",
                    'max' => "  |> max(column: \"{$field}\")\n",
                    'first' => "  |> first(column: \"{$field}\")\n",
                    'last' => "  |> last(column: \"{$field}\")\n",
                    'stddev' => "  |> stddev(column: \"{$field}\")\n",
                    default => str_starts_with($function, 'percentile_')
                        ? '  |> quantile(q: '.substr($function, 11).", column: \"{$field}\")\n"
                        : "  |> {$function}(column: \"{$field}\")\n"
                };

                // Add alias if specified
                if ($alias) {
                    $fluxQuery .= "  |> rename(columns: {_value: \"{$alias}\"})\n";
                }
            }
        }

        // Add fill policy for handling missing data
        if ($query->getFillPolicy()) {
            $policy = $query->getFillPolicy();
            $value = $query->getFillValue();

            $fluxQuery .= match ($policy) {
                'null' => "  |> fill(value: null)\n",
                'none' => '', // In Flux, not filling is the default behavior
                'previous' => "  |> fill(usePrevious: true)\n",
                'linear' => "  |> interpolate.linear()\n",
                'value' => $value !== null ? "  |> fill(value: {$value})\n" : '',
                default => '',
            };
        }

        // Add mathematical expressions
        foreach ($query->getMathExpressions() as $math) {
            $expression = $math['expression'];
            $alias = $math['alias'];

            // In Flux, we need to use map() to apply mathematical expressions
            $fluxQuery .= "  |> map(fn: (r) => ({ r with {$alias}: {$expression} }))\n";
        }

        // Add having clauses (post-aggregation filtering)
        foreach ($query->getHaving() as $having) {
            $field = $having['field'];
            $operator = $this->mapOperator($having['operator']);
            $value = $this->formatValue($having['value']);

            $fluxQuery .= "  |> filter(fn: (r) => r[\"$field\"] $operator $value)\n";
        }

        // Add ordering (sort)
        if (! empty($query->getOrderBy())) {
            foreach ($query->getOrderBy() as $field => $direction) {
                $desc = strtoupper($direction) === 'DESC' ? 'true' : 'false';
                $fluxQuery .= "  |> sort(columns: [\"{$field}\"], desc: {$desc})\n";
            }
        }

        // Add offset
        if ($query->getOffset() !== null) {
            $fluxQuery .= "  |> tail(offset: {$query->getOffset()})\n";
        }

        // Add limit
        if ($query->getLimit() !== null) {
            $fluxQuery .= "  |> limit(n: {$query->getLimit()})\n";
        }

        // Remove trailing newline if present
        $fluxQuery = rtrim($fluxQuery);

        return new RawQuery($fluxQuery);
    }

    private function mapOperator(string $operator): string
    {
        // Map SQL-like operators to Flux operators
        $operatorMap = [
            '=' => '==',
            '!=' => '!=',
            '<>' => '!=',
            '>' => '>',
            '>=' => '>=',
            '<' => '<',
            '<=' => '<=',
            'LIKE' => '=~',
        ];

        return $operatorMap[$operator] ?? $operator;
    }

    /**
     * @throws QueryException
     */
    private function formatValue(mixed $value): string
    {
        return match (true) {
            is_string($value) => '"'.addslashes($value).'"',
            is_bool($value) => $value ? 'true' : 'false',
            is_null($value) => 'null',
            $value instanceof \DateTime => 'time(v: "'.$value->format('c').'")',
            is_numeric($value) => strval($value),
            default => throw new QueryException('Unsupported value type: '.gettype($value).' ('.var_export($value, true).')')
        };
    }

    private function formatDateInterval(\DateInterval $interval): string
    {
        // Convert DateInterval to Flux duration format
        $duration = '';

        if ($interval->y > 0) {
            $duration .= $interval->y.'y';
        }
        if ($interval->m > 0) {
            $duration .= $interval->m.'mo';
        }
        if ($interval->d > 0) {
            $duration .= $interval->d.'d';
        }
        if ($interval->h > 0) {
            $duration .= $interval->h.'h';
        }
        if ($interval->i > 0) {
            $duration .= $interval->i.'m';
        }
        if ($interval->s > 0) {
            $duration .= $interval->s.'s';
        }

        return $duration ?: '0s';
    }

    private function convertIntervalToDuration(string $interval): string
    {
        // Simple conversion from common interval formats to Flux duration
        // This is a simplified implementation - extend as needed
        if (preg_match('/^(\d+)([smhdw])$/', $interval, $matches)) {
            $amount = $matches[1];
            $unit = $matches[2];

            return match ($unit) {
                's' => "{$amount}s",
                'm' => "{$amount}m",
                'h' => "{$amount}h",
                'd' => "{$amount}d",
                'w' => "{$amount}w",
            };
        }

        // Default fallback
        return $interval;
    }
}

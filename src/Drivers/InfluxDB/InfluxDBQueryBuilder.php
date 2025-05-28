<?php

namespace TimeSeriesPhp\Drivers\InfluxDB;

use TimeSeriesPhp\Core\Query;
use TimeSeriesPhp\Core\QueryBuilderInterface;
use TimeSeriesPhp\Core\RawQuery;
use TimeSeriesPhp\Core\RawQueryInterface;
use TimeSeriesPhp\Exceptions\QueryException;

class InfluxDBQueryBuilder implements QueryBuilderInterface
{
    private string $bucket;

    public function __construct(string $bucket)
    {
        $this->bucket = $bucket;
    }

    /**
     * @throws QueryException
     */
    public function build(Query $query): RawQueryInterface
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
        } elseif ($query->getRelativeTime()) {
            // Handle relative time (e.g., "last 1h")
            $fluxQuery .= '  |> range(start: -'.$this->formatDateInterval($query->getRelativeTime()).")\n";
        } else {
            // Default to last hour if no time range specified
            $fluxQuery .= "  |> range(start: -1h)\n";
        }

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
            $value = $this->formatValue($condition->getValue());
            $type = $condition->getType();

            // For InfluxDB, we need to handle different types of conditions
            if ($field === 'time') {
                // Time-based conditions are handled differently
                if ($operator === '==') {
                    $fluxQuery .= "  |> filter(fn: (r) => r._time == {$value})\n";
                } elseif ($operator === '>') {
                    $fluxQuery .= "  |> filter(fn: (r) => r._time > {$value})\n";
                } elseif ($operator === '>=') {
                    $fluxQuery .= "  |> filter(fn: (r) => r._time >= {$value})\n";
                } elseif ($operator === '<') {
                    $fluxQuery .= "  |> filter(fn: (r) => r._time < {$value})\n";
                } elseif ($operator === '<=') {
                    $fluxQuery .= "  |> filter(fn: (r) => r._time <= {$value})\n";
                } elseif ($operator === '!=') {
                    $fluxQuery .= "  |> filter(fn: (r) => r._time != {$value})\n";
                }
            } elseif ($operator === 'IN') {
                // Handle IN operator
                $values = array_map(fn ($v) => $this->formatValue($v), $condition->getValues());
                $valuesList = implode(', ', $values);
                $fluxQuery .= "  |> filter(fn: (r) => contains(value: r[\"$field\"], set: [$valuesList]))\n";
            } elseif ($operator === 'NOT IN') {
                // Handle NOT IN operator
                $values = array_map(fn ($v) => $this->formatValue($v), $condition->getValues());
                $valuesList = implode(', ', $values);
                $fluxQuery .= "  |> filter(fn: (r) => not contains(value: r[\"$field\"], set: [$valuesList]))\n";
            } elseif ($operator === 'BETWEEN' && is_array($condition->getValue()) && count($condition->getValue()) === 2) {
                // Handle BETWEEN operator
                $min = $this->formatValue($condition->getValue()[0]);
                $max = $this->formatValue($condition->getValue()[1]);
                $fluxQuery .= "  |> filter(fn: (r) => r[\"$field\"] >= $min and r[\"$field\"] <= $max)\n";
            } elseif ($operator === 'REGEX') {
                // Handle REGEX operator
                $pattern = $condition->getScalarValue();
                $fluxQuery .= "  |> filter(fn: (r) => r[\"$field\"] =~ /$pattern/)\n";
            } else {
                // Standard operators
                $fluxQuery .= "  |> filter(fn: (r) => r[\"$field\"] $operator $value)\n";
            }
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
        foreach ($query->getAggregations() as $agg) {
            $function = strtolower($agg['function']);
            $field = $agg['field'] ?? '_value';
            $alias = $agg['alias'] ?? null;

            // Apply aggregation
            switch ($function) {
                case 'mean':
                case 'avg':
                    $fluxQuery .= "  |> mean(column: \"{$field}\")\n";
                    break;
                case 'sum':
                    $fluxQuery .= "  |> sum(column: \"{$field}\")\n";
                    break;
                case 'count':
                    $fluxQuery .= "  |> count(column: \"{$field}\")\n";
                    break;
                case 'min':
                    $fluxQuery .= "  |> min(column: \"{$field}\")\n";
                    break;
                case 'max':
                    $fluxQuery .= "  |> max(column: \"{$field}\")\n";
                    break;
                case 'first':
                    $fluxQuery .= "  |> first(column: \"{$field}\")\n";
                    break;
                case 'last':
                    $fluxQuery .= "  |> last(column: \"{$field}\")\n";
                    break;
                case 'stddev':
                    $fluxQuery .= "  |> stddev(column: \"{$field}\")\n";
                    break;
                default:
                    // Handle percentile
                    if (strpos($function, 'percentile_') === 0) {
                        $percentile = substr($function, 11);
                        $fluxQuery .= "  |> quantile(q: {$percentile}, column: \"{$field}\")\n";
                    } else {
                        // For custom or unsupported aggregations, try to use them directly
                        $fluxQuery .= "  |> {$function}(column: \"{$field}\")\n";
                    }
            }

            // Add alias if specified
            if ($alias) {
                $fluxQuery .= "  |> rename(columns: {_value: \"{$alias}\"})\n";
            }
        }

        // Add fill policy for handling missing data
        if ($query->getFillPolicy()) {
            $policy = $query->getFillPolicy();
            $value = $query->getFillValue();

            switch ($policy) {
                case 'null':
                    $fluxQuery .= "  |> fill(value: null)\n";
                    break;
                case 'none':
                    // In Flux, not filling is the default behavior
                    break;
                case 'previous':
                    $fluxQuery .= "  |> fill(usePrevious: true)\n";
                    break;
                case 'linear':
                    $fluxQuery .= "  |> interpolate.linear()\n";
                    break;
                case 'value':
                    if ($value !== null) {
                        $fluxQuery .= "  |> fill(value: {$value})\n";
                    }
                    break;
            }
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
        if (is_string($value)) {
            return '"'.addslashes($value).'"';
        } elseif (is_bool($value)) {
            return $value ? 'true' : 'false';
        } elseif (is_null($value)) {
            return 'null';
        } elseif ($value instanceof \DateTime) {
            return 'time(v: "'.$value->format('c').'")';
        } elseif (is_numeric($value)) {
            return strval($value);
        }

        throw new QueryException(new RawQuery(''), 'Unsupported value type: '.gettype($value).' ('.var_export($value, true).')');
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

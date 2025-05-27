<?php

namespace TimeSeriesPhp\Drivers\RRDtool;

use InvalidArgumentException;
use TimeSeriesPhp\Core\Query;
use TimeSeriesPhp\Core\QueryBuilderContract;
use TimeSeriesPhp\Core\RawQueryContract;
use TimeSeriesPhp\Drivers\RRDtool\Tags\RRDTagStrategyContract;
use TimeSeriesPhp\Drivers\RRDtool\Tags\TagCondition;

class RRDtoolQueryBuilder implements QueryBuilderContract
{
    private RRDTagStrategyContract $tagStrategy;

    public function __construct(RRDTagStrategyContract $tagStrategy)
    {
        $this->tagStrategy = $tagStrategy;
    }

    public function build(Query $query): RawQueryContract
    {
        // Validate the query first
        $errors = $query->validate();
        if (! empty($errors)) {
            throw new InvalidArgumentException('Query validation failed: '.implode(', ', $errors));
        }

        $rawQuery = new RRDtoolRawQuery;
        $measurement = $query->getMeasurement();

        // Handle time range
        $this->buildTimeRange($rawQuery, $query);

        // Get RRD file paths based on conditions (tags)
        $rrdPaths = $this->resolveRRDPaths($query);

        // Build data definitions and calculations
        $this->buildDataDefinitions($rawQuery, $query, $rrdPaths);

        // Handle aggregations
        if ($query->hasAggregations()) {
            $this->buildAggregations($rawQuery, $query);
        }

        // Handle mathematical expressions
        $this->buildMathExpressions($rawQuery, $query);

        // Build export definitions
        $this->buildExports($rawQuery, $query);

        return $rawQuery;
    }

    private function buildTimeRange(RRDtoolRawQuery $rawQuery, Query $query): void
    {
        // Handle relative time
        if ($relativeTime = $query->getRelativeTime()) {
            $rawQuery->param('--start', $this->formatRelativeTime($relativeTime));
        } else {
            // Handle absolute start time
            if ($startTime = $query->getStartTime()) {
                $rawQuery->param('--start', $startTime->format('U'));
            } else {
                // Default to last hour if not specified
                $rawQuery->param('--start', 'end-1h');
            }
        }

        // Handle end time
        if ($endTime = $query->getEndTime()) {
            $rawQuery->param('--end', $endTime->format('U'));
        }

        // Handle time interval for grouping
        if ($query->hasTimeGrouping()) {
            $rawQuery->param('--step', $this->parseInterval($query->getInterval()));
        }
    }

    private function resolveRRDPaths(Query $query): array
    {
        $measurement = $query->getMeasurement();
        $conditions = $query->getConditions();

        // Extract tag conditions to determine which RRD files to query
        $tagConditions = [];
        foreach ($conditions as $condition) {
            // Assuming tag conditions are identifiable by context
            // This might need adjustment based on your tag strategy
            if ($condition['operator'] === '=' || $condition['operator'] === 'IN') {
                $tagConditions[] = new TagCondition($condition['field'], $condition['operator'], $condition['value']);
            }
        }

        // Get RRD file paths based on tag conditions
        return $this->tagStrategy->resolveFilePaths($measurement, $tagConditions);
    }

    private function buildDataDefinitions(RRDtoolRawQuery $rawQuery, Query $query, array $rrdPaths): void
    {
        $fields = $query->getFields();
        $conditions = $query->getConditions();

        // If no specific fields selected or wildcard, determine from RRD structure
        if (empty($fields) || in_array('*', $fields)) {
            $fields = $this->getAvailableFields($rrdPaths);
        }

        $varCounter = 1;
        foreach ($rrdPaths as $rrdPath) {
            foreach ($fields as $field) {
                if ($field === '*') {
                    continue;
                }

                $varName = "v{$varCounter}";

                // Default to AVERAGE consolidation function
                $cf = 'AVERAGE';

                // Apply field-level conditions if any
                if ($this->hasFieldConditions($field, $conditions)) {
                    // For RRDtool, we'll handle filtering post-aggregation
                    // as RRD doesn't support complex WHERE clauses natively
                }

                $rawQuery->def($varName, $rrdPath, $field, $cf);
                $varCounter++;
            }
        }
    }

    private function buildAggregations(RRDtoolRawQuery $rawQuery, Query $query): void
    {
        $aggregations = $query->getAggregations();
        $varCounter = 1000; // Use higher numbers for aggregation variables

        foreach ($aggregations as $agg) {
            $function = strtoupper($agg['function']);
            $field = $agg['field'];
            $alias = $agg['alias'] ?? $field.'_'.strtolower($function);

            $varName = "agg{$varCounter}";

            switch ($function) {
                case 'SUM':
                    // Sum all matching data sources
                    $this->buildSumAggregation($rawQuery, $varName, $field);
                    break;

                case 'AVG':
                case 'AVERAGE':
                    // Average all matching data sources
                    $this->buildAvgAggregation($rawQuery, $varName, $field);
                    break;

                case 'MIN':
                    // Minimum across all data sources
                    $this->buildMinAggregation($rawQuery, $varName, $field);
                    break;

                case 'MAX':
                    // Maximum across all data sources
                    $this->buildMaxAggregation($rawQuery, $varName, $field);
                    break;

                case 'COUNT':
                    // Count non-null values
                    $this->buildCountAggregation($rawQuery, $varName, $field);
                    break;

                case 'FIRST':
                    // Use VDEF to get first value
                    $rawQuery->vdef($varName, 'v1,FIRST');
                    break;

                case 'LAST':
                    // Use VDEF to get last value
                    $rawQuery->vdef($varName, 'v1,LAST');
                    break;

                case 'STDDEV':
                    // Standard deviation using RPN
                    $this->buildStddevAggregation($rawQuery, $varName, $field);
                    break;

                default:
                    if (str_starts_with($function, 'PERCENTILE_')) {
                        $percentile = floatval(substr($function, 11));
                        $rawQuery->vdef($varName, "v1,{$percentile},PERCENT");
                    }
                    break;
            }

            $varCounter++;
        }
    }

    private function buildMathExpressions(RRDtoolRawQuery $rawQuery, Query $query): void
    {
        $mathExpressions = $query->getMathExpressions();
        $varCounter = 2000; // Use even higher numbers for math expressions

        foreach ($mathExpressions as $expr) {
            $varName = "math{$varCounter}";
            $expression = $this->translateMathExpression($expr['expression']);

            $rawQuery->cdef($varName, $expression);
            $varCounter++;
        }
    }

    private function buildExports(RRDtoolRawQuery $rawQuery, Query $query): void
    {
        $fields = $query->getFields();
        $aggregations = $query->getAggregations();
        $mathExpressions = $query->getMathExpressions();

        // Export aggregated values if present
        if (! empty($aggregations)) {
            $varCounter = 1000;
            foreach ($aggregations as $agg) {
                $alias = $agg['alias'] ?? $agg['field'].'_'.strtolower($agg['function']);
                $rawQuery->xport("agg{$varCounter}", $alias);
                $varCounter++;
            }
        }

        // Export math expressions if present
        if (! empty($mathExpressions)) {
            $varCounter = 2000;
            foreach ($mathExpressions as $expr) {
                $rawQuery->xport("math{$varCounter}", $expr['alias']);
                $varCounter++;
            }
        }

        // Export regular fields if no aggregations
        if (empty($aggregations) && empty($mathExpressions)) {
            if (empty($fields) || in_array('*', $fields)) {
                $fields = ['value']; // Default field name
            }

            $varCounter = 1;
            foreach ($fields as $field) {
                if ($field !== '*') {
                    $rawQuery->xport("v{$varCounter}", $field);
                    $varCounter++;
                }
            }
        }
    }

    // Helper methods for specific aggregations
    private function buildSumAggregation(RRDtoolRawQuery $rawQuery, string $varName, ?string $field): void
    {
        // This is a simplified example - you'd need to identify all matching variables
        $rawQuery->cdef($varName, 'v1,v2,+'); // Add more variables as needed
    }

    private function buildAvgAggregation(RRDtoolRawQuery $rawQuery, string $varName, ?string $field): void
    {
        // Calculate average using RPN
        $rawQuery->cdef($varName, 'v1,v2,+,2,/'); // Simplified for 2 variables
    }

    private function buildMinAggregation(RRDtoolRawQuery $rawQuery, string $varName, ?string $field): void
    {
        $rawQuery->cdef($varName, 'v1,v2,MIN');
    }

    private function buildMaxAggregation(RRDtoolRawQuery $rawQuery, string $varName, ?string $field): void
    {
        $rawQuery->cdef($varName, 'v1,v2,MAX');
    }

    private function buildCountAggregation(RRDtoolRawQuery $rawQuery, string $varName, ?string $field): void
    {
        // Count non-NaN values
        $rawQuery->cdef($varName, 'v1,UN,0,1,IF,v2,UN,0,1,IF,+');
    }

    private function buildStddevAggregation(RRDtoolRawQuery $rawQuery, string $varName, ?string $field): void
    {
        // Standard deviation calculation using RPN (simplified)
        $rawQuery->vdef($varName, 'v1,STDEV');
    }

    private function translateMathExpression(string $expression): string
    {
        // Translate mathematical expressions to RPN format
        // This is a simplified example - you'd need a proper expression parser
        return str_replace(['+', '-', '*', '/'], [',+', ',-', ',*', ',/'], $expression);
    }

    private function formatRelativeTime(\DateInterval $interval): string
    {
        // Convert DateInterval to RRDtool relative time format
        $seconds = 0;
        $seconds += $interval->s;
        $seconds += $interval->i * 60;
        $seconds += $interval->h * 3600;
        $seconds += $interval->d * 86400;

        return "end-{$seconds}s";
    }

    private function parseInterval(?string $interval): string
    {
        if (! $interval) {
            return '300'; // Default 5 minutes
        }

        // Parse interval like "1m", "5m", "1h", etc.
        if (preg_match('/^(\d+)([smh])$/', $interval, $matches)) {
            $amount = intval($matches[1]);
            $unit = $matches[2];

            return match ($unit) {
                's' => (string) $amount,
                'm' => (string) ($amount * 60),
                'h' => (string) ($amount * 3600),
            };
        }

        return $interval; // Return as-is if already in seconds
    }

    private function getAvailableFields(array $rrdPaths): array
    {
        // This would need to be implemented based on your RRD file structure
        // For now, return a default field name
        return ['value'];
    }

    private function hasFieldConditions(string $field, array $conditions): bool
    {
        foreach ($conditions as $condition) {
            if ($condition['field'] === $field) {
                return true;
            }
        }

        return false;
    }
}

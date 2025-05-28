<?php

namespace TimeSeriesPhp\Core;

use DateInterval;
use DateTime;

class Query
{
    private string $measurement;

    /**
     * @var array<int, string>
     */
    private array $fields = ['*'];

    /**
     * @var array<int, array{'field': string, 'operator': string, 'value': mixed, 'type'?: 'AND'|'OR'}>
     */
    private array $conditions = [];

    private ?DateTime $startTime = null;

    private ?DateTime $endTime = null;

    private ?DateInterval $relativeTime = null;

    /**
     * @var string[]
     */
    private array $groupBy = [];

    /**
     * @var array<int, array{'function': string, 'field': ?string, 'alias': ?string}>
     */
    private array $aggregations = [];

    private ?string $interval = null;

    private ?int $limit = null;

    private ?int $offset = null;

    /**
     * @var array<string, 'ASC'|'DESC'>
     */
    private array $orderBy = [];

    /**
     * @var array<array{'field': string, 'operator': string, 'value': mixed}>
     */
    private array $having = [];

    private ?string $fillPolicy = null;

    private ?float $fillValue = null;

    /**
     * @var array<array{'expression': string, 'alias': string}>
     */
    private array $mathExpressions = [];

    private bool $distinct = false;

    private ?string $timezone = null;

    public function __construct(string $measurement)
    {
        $this->measurement = $measurement;
    }

    // Enhanced field selection with aliases and calculations

    /**
     * @param  string[]  $fields
     */
    public function select(array $fields): self
    {
        $this->fields = $fields;

        return $this;
    }

    /**
     * @param  string[]  $fields
     */
    public function selectDistinct(array $fields): self
    {
        $this->fields = $fields;
        $this->distinct = true;

        return $this;
    }

    // More flexible condition building
    public function where(string $field, string $operator, mixed $value): self
    {
        $this->conditions[] = [
            'field' => $field,
            'operator' => $operator,
            'value' => $value,
            'type' => 'AND',
        ];

        return $this;
    }

    public function orWhere(string $field, string $operator, mixed $value): self
    {
        $this->conditions[] = [
            'field' => $field,
            'operator' => $operator,
            'value' => $value,
            'type' => 'OR',
        ];

        return $this;
    }

    /**
     * @param  array<int, mixed>  $values
     */
    public function whereIn(string $field, array $values): self
    {
        $this->conditions[] = [
            'field' => $field,
            'operator' => 'IN',
            'value' => $values,
            'type' => 'AND',
        ];

        return $this;
    }

    /**
     * @param  array<int, mixed>  $values
     */
    public function whereNotIn(string $field, array $values): self
    {
        $this->conditions[] = [
            'field' => $field,
            'operator' => 'NOT IN',
            'value' => $values,
            'type' => 'AND',
        ];

        return $this;
    }

    public function whereBetween(string $field, int|float $min, int|float $max): self
    {
        $this->conditions[] = [
            'field' => $field,
            'operator' => 'BETWEEN',
            'value' => [$min, $max],
            'type' => 'AND',
        ];

        return $this;
    }

    public function whereRegex(string $field, string $pattern): self
    {
        $this->conditions[] = [
            'field' => $field,
            'operator' => 'REGEX',
            'value' => $pattern,
            'type' => 'AND',
        ];

        return $this;
    }

    // Enhanced time range methods
    public function timeRange(DateTime $start, DateTime $end): self
    {
        $this->startTime = $start;
        $this->endTime = $end;
        $this->relativeTime = null;

        return $this;
    }

    public function since(DateTime $start): self
    {
        $this->startTime = $start;
        $this->endTime = null;
        $this->relativeTime = null;

        return $this;
    }

    public function until(DateTime $end): self
    {
        $this->endTime = $end;

        return $this;
    }

    public function latest(string $duration): self
    {
        // Parse duration like "1h", "30m", "7d", etc.
        $this->relativeTime = $this->parseDuration($duration);
        $this->startTime = null;
        $this->endTime = null;

        return $this;
    }

    public function timezone(string $timezone): self
    {
        $this->timezone = $timezone;

        return $this;
    }

    /**
     * @param  string[]  $tags
     */
    public function groupBy(array $tags, ?string $interval = null): self
    {
        $this->groupBy = $tags;
        if ($interval) {
            $this->interval = $interval;
        }

        return $this;
    }

    public function groupByTime(string $interval): self
    {
        $this->interval = $interval;

        return $this;
    }

    // Support multiple aggregations
    public function aggregate(string $function, ?string $field = null, ?string $alias = null): self
    {
        $this->aggregations[] = [
            'function' => $function,
            'field' => $field,
            'alias' => $alias,
        ];

        return $this;
    }

    // Common aggregation shortcuts
    public function sum(string $field, ?string $alias = null): self
    {
        return $this->aggregate('SUM', $field, $alias);
    }

    public function avg(string $field, ?string $alias = null): self
    {
        return $this->aggregate('AVG', $field, $alias);
    }

    public function count(?string $field = null, ?string $alias = null): self
    {
        return $this->aggregate('COUNT', $field, $alias);
    }

    public function min(string $field, ?string $alias = null): self
    {
        return $this->aggregate('MIN', $field, $alias);
    }

    public function max(string $field, ?string $alias = null): self
    {
        return $this->aggregate('MAX', $field, $alias);
    }

    public function first(string $field, ?string $alias = null): self
    {
        return $this->aggregate('FIRST', $field, $alias);
    }

    public function last(string $field, ?string $alias = null): self
    {
        return $this->aggregate('LAST', $field, $alias);
    }

    // Percentile and statistical functions
    public function percentile(string $field, float $percentile, ?string $alias = null): self
    {
        return $this->aggregate("PERCENTILE_{$percentile}", $field, $alias);
    }

    public function stddev(string $field, ?string $alias = null): self
    {
        return $this->aggregate('STDDEV', $field, $alias);
    }

    // Fill policies for handling missing data
    public function fill(string $policy, ?float $value = null): self
    {
        $this->fillPolicy = $policy;
        $this->fillValue = $value;

        return $this;
    }

    public function fillNull(): self
    {
        return $this->fill('null');
    }

    public function fillNone(): self
    {
        return $this->fill('none');
    }

    public function fillPrevious(): self
    {
        return $this->fill('previous');
    }

    public function fillLinear(): self
    {
        return $this->fill('linear');
    }

    public function fillValue(float $value): self
    {
        return $this->fill('value', $value);
    }

    // Mathematical expressions
    public function math(string $expression, string $alias): self
    {
        $this->mathExpressions[] = [
            'expression' => $expression,
            'alias' => $alias,
        ];

        return $this;
    }

    // Enhanced ordering and limiting
    public function limit(int $limit): self
    {
        $this->limit = $limit;

        return $this;
    }

    public function offset(int $offset): self
    {
        $this->offset = $offset;

        return $this;
    }

    public function orderBy(string $field, string $direction = 'ASC'): self
    {
        $this->orderBy[$field] = strtoupper($direction) == 'DESC' ? 'DESC' : 'ASC';

        return $this;
    }

    public function orderByTime(string $direction = 'ASC'): self
    {
        return $this->orderBy('time', $direction);
    }

    public function having(string $field, string $operator, mixed $value): self
    {
        $this->having[] = [
            'field' => $field,
            'operator' => $operator,
            'value' => $value,
        ];

        return $this;
    }

    // Utility methods
    private function parseDuration(string $duration): DateInterval
    {
        // Simple duration parser - extend as needed
        $matches = [];
        if (preg_match('/^(\d+)([smhdwy])$/', $duration, $matches)) {
            $amount = (int) $matches[1];
            $unit = $matches[2];

            switch ($unit) {
                case 's': return new DateInterval("PT{$amount}S");
                case 'm': return new DateInterval("PT{$amount}M");
                case 'h': return new DateInterval("PT{$amount}H");
                case 'd': return new DateInterval("P{$amount}D");
                case 'w': return new DateInterval('P'.($amount * 7).'D');
                case 'y': return new DateInterval("P{$amount}Y");
            }
        }
        throw new \InvalidArgumentException("Invalid duration format: {$duration}");
    }

    // Enhanced getters
    public function getMeasurement(): string
    {
        return $this->measurement;
    }

    /**
     * @return array<int, string>
     */
    public function getFields(): array
    {
        return $this->fields;
    }

    /**
     * @return array<int, array{'field': string, 'operator': string, 'value': mixed, 'type'?: 'AND'|'OR'}>
     */
    public function getConditions(): array
    {
        return $this->conditions;
    }

    public function getStartTime(): ?DateTime
    {
        return $this->startTime;
    }

    public function getEndTime(): ?DateTime
    {
        return $this->endTime;
    }

    public function getRelativeTime(): ?DateInterval
    {
        return $this->relativeTime;
    }

    /**
     * @return string[]
     */
    public function getGroupBy(): array
    {
        return $this->groupBy;
    }

    /**
     * @return array<int, array{'function': string, 'field': ?string, 'alias': ?string}>
     */
    public function getAggregations(): array
    {
        return $this->aggregations;
    }

    public function getInterval(): ?string
    {
        return $this->interval;
    }

    public function getLimit(): ?int
    {
        return $this->limit;
    }

    public function getOffset(): ?int
    {
        return $this->offset;
    }

    /**
     * @return array<string, 'ASC'|'DESC'>
     */
    public function getOrderBy(): array
    {
        return $this->orderBy;
    }

    /**
     * @return array<array{'field': string, 'operator': string, 'value': mixed}>
     */
    public function getHaving(): array
    {
        return $this->having;
    }

    public function getFillPolicy(): ?string
    {
        return $this->fillPolicy;
    }

    public function getFillValue(): ?float
    {
        return $this->fillValue;
    }

    /**
     * @return array<array{'expression': string, 'alias': string}>
     */
    public function getMathExpressions(): array
    {
        return $this->mathExpressions;
    }

    public function isDistinct(): bool
    {
        return $this->distinct;
    }

    public function getTimezone(): ?string
    {
        return $this->timezone;
    }

    public function hasAggregations(): bool
    {
        return ! empty($this->aggregations);
    }

    public function hasTimeGrouping(): bool
    {
        return ! empty($this->interval);
    }

    /**
     * @return string[]
     */
    public function validate(): array
    {
        $errors = [];

        if (empty($this->measurement)) {
            $errors[] = 'Measurement is required';
        }

        if ($this->hasAggregations() && empty($this->groupBy) && empty($this->interval)) {
            $errors[] = 'Aggregations require GROUP BY clause or time interval';
        }

        if (! empty($this->having) && ! $this->hasAggregations()) {
            $errors[] = 'HAVING clause requires aggregation functions';
        }

        return $errors;
    }
}

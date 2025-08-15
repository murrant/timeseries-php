<?php

namespace TimeSeriesPhp\Core\Data;

class QueryResult
{
    /**
     * @var array<string, Series> Series objects by name
     */
    private array $seriesObjects = [];

    /**
     * @param  array<string, array<int, array{'date': int|string, 'value': ?scalar}>>  $series
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(private array $series = [], private readonly array $metadata = []) {}

    /**
     * Add a series to the result
     *
     * @param  string|Series  $nameOrSeries  The name of the series or a Series object
     * @param  array<int, string>|null  $columns  The column names (if $nameOrSeries is a string)
     * @param  array<int, array<int, mixed>>|null  $values  The values for each row (if $nameOrSeries is a string)
     * @param  array<string, mixed>  $tags  The tags for the series (if $nameOrSeries is a string)
     */
    public function addSeries(string|Series $nameOrSeries, ?array $columns = null, ?array $values = null, array $tags = []): void
    {
        if ($nameOrSeries instanceof Series) {
            $series = $nameOrSeries;
            $name = $series->getName();
            $columns = $series->getColumns();
            $values = $series->getValues();
            $tags = $series->getTags();

            // Store the Series object
            $this->seriesObjects[$name] = $series;
        } else {
            $name = $nameOrSeries;
            if ($columns === null || $values === null) {
                throw new \InvalidArgumentException('Columns and values must be provided when adding a series by name');
            }

            // Create and store a Series object
            $series = new Series($name, $columns, $values, $tags);
            $this->seriesObjects[$name] = $series;
        }

        foreach ($values as $row) {
            $timestamp = null;
            $timeIndex = array_search('time', $columns);

            if ($timeIndex !== false && isset($row[$timeIndex])) {
                $timestamp = $row[$timeIndex];
            }

            foreach ($columns as $i => $column) {
                if ($column !== 'time' && isset($row[$i])) {
                    $fieldName = $name.'.'.$column;

                    // Ensure timestamp is a valid type
                    $validTimestamp = $timestamp !== null ? (is_string($timestamp) || is_int($timestamp) ? $timestamp : (string) time()) : (string) time();

                    // Ensure value is a valid type
                    $value = $row[$i];
                    $validValue = is_scalar($value) ? (string) $value : null;

                    $this->appendPoint($validTimestamp, $fieldName, $validValue);
                }
            }
        }
    }

    public function appendPoint(int|string $timestamp, string $field, float|int|string|bool|null $value): void
    {
        $this->series[$field][] = ['date' => $timestamp, 'value' => $value];
    }

    public function getSingleValue(?string $field = null): float|int|string|bool|null
    {
        $field ??= array_key_first($this->series);

        return $this->series[$field][0]['value'] ?? null;
    }

    /**
     * Get the series data as an associative array where keys are series names and values are points
     *
     * @return array<string, array<int, array{'date': int|string, 'value': ?scalar}>>
     */
    public function getSeries(): array
    {
        return $this->series;
    }

    /**
     * Get all Series objects (if the result was populated with Series instances)
     *
     * @return array<string, Series> Series objects by name
     */
    public function getSeriesObjects(): array
    {
        return $this->seriesObjects;
    }

    /**
     * @return array<int, int|string>
     */
    public function getTimestamps(): array
    {
        $first = array_key_first($this->series);

        return array_column($this->series[$first], 'date');
    }

    /**
     * @return array<string, mixed>
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function isEmpty(): bool
    {
        return empty($this->series) && empty($this->seriesObjects);
    }

    public function count(): int
    {
        return ! empty($this->series) ? count($this->series) : count($this->seriesObjects);
    }

    /**
     * @return array{'series': array<string, array<int, array{'date': int|string, 'value': ?scalar}>>, 'metadata': array<string, mixed>}
     */
    public function toArray(): array
    {
        return [
            'series' => $this->series,
            'metadata' => $this->metadata,
        ];
    }
}

<?php

namespace TimeSeriesPhp\Core\Data;

class QueryResult
{
    /**
     * @param  array<string, array<int, array{'date': int|string, 'value': ?scalar}>>  $series
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(private array $series = [], private readonly array $metadata = []) {}

    /**
     * Add a series to the result
     *
     * @param  string  $name  The name of the series
     * @param  array<int, string>  $columns  The column names
     * @param  array<int, array<int, mixed>>  $values  The values for each row
     * @param  array<string, mixed>  $tags  The tags for the series
     */
    public function addSeries(string $name, array $columns, array $values, array $tags = []): void
    {
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
     * @return array<string, array<int, array{'date': int|string, 'value': ?scalar}>>
     */
    public function getSeries(): array
    {
        return $this->series;
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
        return empty($this->series);
    }

    public function count(): int
    {
        return count($this->series);
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

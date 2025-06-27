<?php

namespace TimeSeriesPhp\Core\Data;

/**
 * Class representing a series of data points
 */
class Series
{
    /**
     * @param  string  $name  The name of the series
     * @param  array<int, string>  $columns  The column names
     * @param  array<int, array<int, mixed>>  $values  The values for each row
     * @param  array<string, mixed>  $tags  The tags for the series
     */
    public function __construct(
        private readonly string $name,
        private readonly array $columns,
        private readonly array $values,
        private readonly array $tags = []
    ) {}

    /**
     * Get the name of the series
     *
     * @return string The name of the series
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get the column names
     *
     * @return array<int, string> The column names
     */
    public function getColumns(): array
    {
        return $this->columns;
    }

    /**
     * Get the values for each row
     *
     * @return array<int, array<int, mixed>> The values
     */
    public function getValues(): array
    {
        return $this->values;
    }

    /**
     * Get the tags for the series
     *
     * @return array<string, mixed> The tags
     */
    public function getTags(): array
    {
        return $this->tags;
    }
}

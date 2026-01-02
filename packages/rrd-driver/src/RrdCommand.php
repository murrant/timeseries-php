<?php

namespace TimeseriesPhp\Driver\RRD;

use TimeseriesPhp\Core\Contracts\CompiledQuery;
use TimeseriesPhp\Core\Results\TimeSeriesResult;

/**
 * @implements CompiledQuery<TimeSeriesResult>
 */
readonly class RrdCommand implements \Stringable, CompiledQuery
{
    /**
     * @param  array<int|string, string>  $options
     * @param  string[]  $arguments  DEF,CDEF,VDEF,XPORT,etc expressions
     */
    public function __construct(
        public string $name,
        public array $options,
        public array $arguments,
    ) {}

    public function expandedOptions(): array
    {
        $options = [];

        foreach ($this->options as $key => $value) {
            if (is_string($key)) {
                array_push($options, $key, $value);
            } else {
                $options[] = $value;
            }
        }

        return $options;
    }

    public function __toString(): string
    {
        return implode(' ', [$this->name, ...$this->expandedOptions(), ...$this->arguments]);
    }
}

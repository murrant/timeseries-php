<?php

namespace TimeSeriesPhp\Drivers\RRDtool;

use TimeSeriesPhp\Core\RawQueryContract;

class RRDRawQuery implements RawQueryContract
{
    protected array $parameters = [];

    public function __construct(
        public readonly string $type = 'xport'
    ) {
    }

    public function param(string $param, ?string $value = null): self
    {
        $this->parameters[$param] = $value;

        return $this;
    }


    public function getRawQuery(): string
    {
        $rawQuery = $this->type . ' ';

        foreach ($this->parameters as $param => $value) {
            if ($value === null) {
                $rawQuery .= escapeshellarg($param) . ' ';
            } else {
                $rawQuery .= escapeshellarg($param) . ' ' . escapeshellarg($value) . ' ';
            }
        }

        return $rawQuery;
    }
}

<?php

namespace TimeSeriesPhp\Drivers\RRDtool;

use TimeSeriesPhp\Core\RawQueryContract;

class RRDtoolRawQuery implements RawQueryContract
{
    /**
     * @var array<string, ?string>
     */
    protected array $parameters = [];

    /**
     * @var array<array{string, string}>
     */
    protected array $data = [];

    public function __construct(
        public readonly string $type = 'xport'
    ) {
        if ($type === 'xport') {
            $this->param('--json');
        }
    }

    public function param(string $param, ?string $value = null): self
    {
        $this->parameters[$param] = $value;

        return $this;
    }

    // DEF:<vname>=<rrdfile>:<ds-name>:<CF>[:step=<step>][:start=<time>][:end=<time>][:reduce=<CF>][:daemon=<address>]
    public function def(string $vname, string $rrdfile, string $dsName, string $cf, ?string $step = null, ?string $start = null, ?string $end = null, ?string $reduce = null, ?string $daemon = null): self
    {
        $vars = [
            'DEF',
            "$vname=$rrdfile",
            $dsName,
            $cf,
        ];

        if ($step) {
            $vars[] = "step=$step";
        }

        if ($start) {
            $vars[] = "start=$start";
        }

        if ($end) {
            $vars[] = "end=$end";
        }

        if ($reduce) {
            $vars[] = "reduce=$reduce";
        }

        if ($daemon) {
            $vars[] = "daemon=$daemon";
        }

        $this->data[] = $vars;

        return $this;
    }

    // CDEF:vname=RPN expression
    public function cdef(string $vname, string $rpnExpression): self
    {
        $this->data[] = [
            'CDEF',
            "$vname=$rpnExpression",
        ];

        return $this;
    }

    // VDEF:vname=RPN expression
    public function vdef(string $vname, string $rpnExpression): self
    {
        $this->data[] = [
            'VDEF',
            "$vname=$rpnExpression",
        ];

        return $this;
    }

    public function xport(string $vname, ?string $legend = null): self
    {
        $this->data[] = [
            'XPORT',
            $vname.($legend ? ":$legend" : ''),
        ];

        return $this;
    }

    public function getRawQuery(): string
    {
        $rawQuery = $this->type.' ';

        foreach ($this->parameters as $param => $value) {
            if ($value === null) {
                $rawQuery .= escapeshellarg($param).' ';
            } else {
                $rawQuery .= escapeshellarg($param).' '.escapeshellarg($value).' ';
            }
        }

        foreach ($this->data as $data) {
            $rawQuery .= escapeshellarg(implode(':', $data)).' ';
        }

        return $rawQuery;
    }

    /**
     * @return string[]
     */
    public function getFields(): array
    {
        $fields = [];

        foreach ($this->data as $data) {
            if ($data[0] === 'XPORT') {
                $fields[] = substr($data[1], 0, strpos($data[1], ':') ?: null);
            }
        }

        return $fields;
    }
}

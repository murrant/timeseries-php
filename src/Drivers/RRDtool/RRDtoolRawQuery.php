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
        public readonly string $command = 'xport',
        public string $filename = '',
    ) {
        if ($command === 'xport') {
            $this->param('--json');
        }
        if ($command === 'graph' && empty($filename)) {
            $this->filename = '-'; // stdout
        }
    }

    public function param(string $param, int|string|null $value = null): self
    {
        $this->parameters[$param] = $value !== null ? $this->escapeString((string) $value) : null;

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
            $vname.($legend ? ':'.$this->escapeString($legend) : ''),
        ];

        return $this;
    }

    public function getRawQuery(): string
    {
        $args = $this->getArgs();
        array_unshift($args, $this->command);

        return implode(' ', array_map(fn ($arg) => escapeshellarg($arg), $args));
    }

    /**
     * @return string[]
     */
    public function getArgs(): array
    {
        $args = $this->filename ? [$this->filename] : [];

        foreach ($this->parameters as $param => $value) {
            if ($value === null) {
                $args[] = $param;
            } else {
                array_push($args, $param, $value);
            }
        }

        foreach ($this->data as $data) {
            $args[] = implode(':', $data);
        }

        return $args;
    }

    public function getParam(string $param): ?string
    {
        return $this->parameters[$param] ?? null;
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

    private function escapeString(string $string): string
    {
        $str = addcslashes($string, ":'\"\\");
        if (str_contains($str, ' ')) {
            $str = '"'.$str.'"';
        }

        return $str;
    }
}

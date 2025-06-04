<?php

namespace TimeSeriesPhp\Drivers\RRDtool;

use TimeSeriesPhp\Contracts\Query\RawQueryInterface;

class RRDtoolRawQuery implements RawQueryInterface
{
    /**
     * @var array<string, ?string>
     */
    protected array $parameters = [];

    /**
     * @var array<string[]>
     */
    protected array $statements = [];

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

    public function statement(string $type, string ...$params): self
    {
        $this->statements[] = [$type, ...$params];

        return $this;
    }

    // DEF:<vname>=<rrdfile>:<ds-name>:<CF>[:step=<step>][:start=<time>][:end=<time>][:reduce=<CF>][:daemon=<address>]
    public function def(string $vname, string $rrdfile, string $dsName, string $cf, ?string $step = null, ?string $start = null, ?string $end = null, ?string $reduce = null, ?string $daemon = null): self
    {
        $vars = [
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

        $this->statement('DEF', ...$vars);

        return $this;
    }

    // CDEF:vname=RPN expression
    public function cdef(string $vname, string $rpnExpression): self
    {
        $this->statement('CDEF', "$vname=$rpnExpression");

        return $this;
    }

    // VDEF:vname=RPN expression
    public function vdef(string $vname, string $rpnExpression): self
    {
        $this->statement('VDEF', "$vname=$rpnExpression");

        return $this;
    }

    public function xport(string $vname, ?string $legend = null): self
    {
        if ($legend) {
            $this->statement('XPORT', $vname, $this->escapeString($legend));

            return $this;
        }

        $this->statement('XPORT', $vname);

        return $this;
    }

    /**
     * Add a LINE statement to the graph
     *
     * @param  string  $width  Line width (1, 2, 3)
     * @param  string  $value  Data source name
     * @param  string  $color  Color in #RRGGBB format
     * @param  string|null  $legend  Legend text
     * @param  bool  $stack  Whether to stack this line on top of the previous one
     */
    public function line(string $width, string $value, string $color, ?string $legend = null, bool $stack = false): self
    {
        // Ensure color starts with #
        if (! str_starts_with($color, '#')) {
            $color = '#'.$color;
        }

        // Format: LINE[width]:value[#color][:[legend][:STACK]]
        $lineValue = $value.$color;

        if ($legend) {
            if ($stack) {
                $this->statement('LINE'.$width, $lineValue, $this->escapeString($legend), 'STACK');
            } else {
                $this->statement('LINE'.$width, $lineValue, $this->escapeString($legend));
            }
        } else {
            $this->statement('LINE'.$width, $lineValue);
        }

        return $this;
    }

    public function getRawQuery(): string
    {
        // FIXME: use escapeshellarg() but it keeps using double quotes for some reason
        $args = $this->getArgs();
        $queryString = "'$this->command'";

        foreach ($args as $arg) {
            $queryString .= " '{$arg}'";
        }

        return $queryString;
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

        foreach ($this->statements as $data) {
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

        foreach ($this->statements as $data) {
            if ($data[0] === 'XPORT') {
                $fields[] = substr($data[1], 0, strpos($data[1], ':') ?: null);
            }
        }

        return $fields;
    }

    private function escapeString(string $string): string
    {
        // Only escape colons and backslashes, as they are special in RRDtool syntax
        // Don't escape quotes or add additional quotes, as this can cause issues
        return addcslashes($string, ':\\');
    }
}

<?php

namespace TimeseriesPhp\Driver\RRD\Traits;

use TimeseriesPhp\Driver\RRD\Exceptions\InvalidRrdtoolOutput;

trait RrdOutputParser
{
    private function parseInfoOutput(string $output): array
    {
        if (!preg_match(
            '/^
            filename\s*=\s*"(?<filename>[^"]+)"\R
            rrd_version\s*=\s*"(?<rrd_version>[^"]+)"\R
            step\s*=\s*(?<step>\d+)\R
            last_update\s*=\s*(?<last_update>\d+)\R
            header_size\s*=\s*(?<header_size>\d+)\R
            \R?
            (?<ds>(?:^ds\[.*$\R?)*)
            (?<rra>(?:^rra\[.*$\R?)*)
        $/mx',
            trim($output),
            $m
        )) {
            throw new InvalidRrdtoolOutput($output, 'info');
        }

        $info = [
            'filename'    => $m['filename'],
            'rrd_version' => $m['rrd_version'],
            'step'        => (int) $m['step'],
            'last_update' => (int) $m['last_update'],
            'header_size' => (int) $m['header_size'],
            'ds'          => [],
            'rra'         => [],
        ];

        /* -------------------------------------------------------------
         * DS accumulation
         * ------------------------------------------------------------- */
        foreach (explode("\n", trim($m['ds'])) as $line) {
            if ($line === '') {
                continue;
            }

            preg_match('/^ds\[([^]]+)]\.([a-z_]+) = (.+)$/', $line, $mm);
            $info['ds'][$mm[1]][$mm[2]] = $this->rrdScalar($mm[3]);
        }

        /* -------------------------------------------------------------
         * RRA accumulation
         * ------------------------------------------------------------- */
        foreach (explode("\n", trim($m['rra'])) as $line) {
            if ($line === '') {
                continue;
            }

            if (str_contains($line, 'cdp_prep')) {
                preg_match('/^rra\[(\d+)]\.cdp_prep\[(\d+)]\.([a-z_]+) = (.+)$/', $line, $mm);
                $info['rra'][(int) $mm[1]]['cdp_prep'][(int) $mm[2]][$mm[3]]
                    = $this->rrdScalar($mm[4]);
            } else {
                preg_match('/^rra\[(\d+)]\.([a-z_]+) = (.+)$/', $line, $mm);
                $info['rra'][(int) $mm[1]][$mm[2]] = $this->rrdScalar($mm[3]);
            }
        }

        return $info;
    }

    /**
     * Parses a single RRD info value into a PHP scalar.
     */
    private function rrdScalar(string $value): string|int|float
    {
        $value = trim($value);

        if ($value === 'NaN') {
            return NAN;
        }

        if ($value[0] === '"') {
            return substr($value, 1, -1);
        }

        return str_contains($value, '.')
        || str_contains($value, 'e')
        || str_contains($value, 'E')
            ? (float) $value
            : (int) $value;
    }
}

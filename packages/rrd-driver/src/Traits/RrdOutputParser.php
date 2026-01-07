<?php

namespace TimeseriesPhp\Driver\RRD\Traits;

use DateTimeImmutable;
use TimeseriesPhp\Core\Query\AST\Resolution;
use TimeseriesPhp\Core\Query\AST\TimeRange;
use TimeseriesPhp\Core\Results\DataPoint;
use TimeseriesPhp\Core\Results\TimeSeries;
use TimeseriesPhp\Core\Results\TimeSeriesQueryResult;
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
     * Parse rrdtool xport JSON output into TimeSeriesQueryResult
     */
    public function parseXportOutput(string $jsonContent): TimeSeriesQueryResult
    {
        $data = json_decode($jsonContent, true, 512, JSON_THROW_ON_ERROR);

        $meta = $data['meta'];
        $start = (int) $meta['start'];
        $end = (int) $meta['end'];
        $step = (int) $meta['step'];
        $legends = $meta['legend'];

        $timeRange = new TimeRange(
            new DateTimeImmutable("@{$start}"),
            new DateTimeImmutable("@{$end}")
        );

        $resolution = new Resolution($step);

        // Initialize time series for each metric
        $seriesData = array_fill_keys(array_keys($legends), []);

        // Parse data rows
        $timestamp = $start;
        foreach ($data['data'] as $row) {
            foreach ($row as $idx => $value) {
                $seriesData[$idx][] = new DataPoint(
                    $timestamp,
                    is_float($value) && is_nan($value) ? null : $value
                );
            }
            $timestamp += $step;
        }

        // Build TimeSeries objects
        $series = [];
        foreach ($legends as $idx => $legend) {
            $metric = $legend;
            $alias = null;

            if (str_contains($legend, ':')) {
                [$metric, $alias] = explode(':', $legend, 2);
            }

            $series[] = new TimeSeries(
                metric: $metric,
                alias: $alias,
                labels: $this->extractLabels($legend),
                points: $seriesData[$idx]
            );
        }

        return new TimeSeriesQueryResult(
            series: $series,
            range: $timeRange,
            resolution: $resolution
        );
    }

    /**
     * Extract labels from legend string
     *
     * @return string[]
     */
    private function extractLabels(string $legend): array
    {
        $labels = [];

        if (preg_match('/\{([^}]+)}/', $legend, $matches)) {
            foreach(explode(',', $matches[1]) as $pair) {
                [$key, $value] = explode('=', $pair, 2);
                $labels[$key] = $value;
            }
        }

        return $labels;
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

<?php

namespace TimeseriesPhp\Driver\RRD;

use InvalidArgumentException;
use TimeseriesPhp\Core\Contracts\CompiledQuery;
use TimeseriesPhp\Core\Contracts\MetricRepository;
use TimeseriesPhp\Core\Contracts\Query;
use TimeseriesPhp\Core\Contracts\QueryCompiler;
use TimeseriesPhp\Core\Contracts\QueryResult;
use TimeseriesPhp\Core\Exceptions\UnknownMetricException;
use TimeseriesPhp\Core\Query\AST\DataQuery;
use TimeseriesPhp\Core\Query\AST\LabelQuery;
use TimeseriesPhp\Core\Results\LabelQueryResult;
use TimeseriesPhp\Core\Results\TimeSeriesQueryResult;
use TimeseriesPhp\Driver\RRD\Contracts\RrdtoolInterface;
use TimeseriesPhp\Driver\RRD\Exceptions\RrdException;
use TimeseriesPhp\Driver\RRD\Exceptions\RrdNotFoundException;

/**
 * @template TResult of QueryResult
 *
 * @implements QueryCompiler<TResult>
 */
readonly class RrdCompiler implements QueryCompiler
{
    public function __construct(
        private RrdConfig $config,
        private MetricRepository $metrics,
        private RrdtoolInterface $rrdtool,
    ) {}

    /**
     * @param  Query<TResult>  $query
     * @return CompiledQuery<TResult>
     *
     * @throws RrdException
     */
    public function compile(Query $query): CompiledQuery
    {
        if ($query instanceof LabelQuery) {
            /** @var CompiledQuery<LabelQueryResult> $rrdLabelQuery */
            $rrdLabelQuery = new RrdLabelQuery($query);

            return $rrdLabelQuery;
        }

        if ($query instanceof DataQuery) {
            /** @var CompiledQuery<TimeSeriesQueryResult> $rrdCommand */
            $rrdCommand = $this->compileDataQuery($query);

            // @phpstan-ignore-next-line
            return $rrdCommand;
        }

        throw new InvalidArgumentException('Unsupported query type: '.$query::class);
    }

    /**
     * @return CompiledQuery<TimeSeriesQueryResult>
     *
     * @throws RrdException
     */
    private function compileDataQuery(DataQuery $query): CompiledQuery
    {
        $options = [
            '--start' => (string) $query->period->start->getTimestamp(),
            '--end' => (string) $query->period->end->getTimestamp(),
        ];

        if ($query->resolution->seconds) {
            $options['--step'] = (string) $query->resolution->seconds;
        }

        $defs = [];
        $cdefs = [];
        $xports = [];

        $strategy = new FilenameLabelStrategy($this->config, $this->rrdtool);

        foreach ($query->streams as $index => $stream) {
            try {
                $metric = $this->metrics->get($stream->metric);
            } catch (UnknownMetricException) {
                continue;
            }

            // Find all files matching the filters
            $files = $strategy->listFilenames($metric, $stream->filters);

            // Create a DEF for each file
            $streamDefs = [];
            foreach ($files as $fileIndex => $file) {
                $defName = "s{$index}f$fileIndex";

                // We need to know the DS name. Assuming 'value' for now or from metric definition?
                // MetricIdentifier doesn't seem to have DS name. RrdWriter usually uses 'value' or similar.
                // Let's assume 'value' for single-value metrics.
                $dsName = 'value';

                // CF (Consolidation Function). Default to AVERAGE?
                $cf = 'AVERAGE';

                $defs[] = "DEF:$defName=$file:$dsName:$cf";
                $streamDefs[] = $defName;
            }

            if (empty($streamDefs)) {
                continue;
            }

            // Aggregate streams if multiple files matched (e.g. sum them up)
            // If no aggregation specified, maybe we should just output them all?
            // But DataQuery usually implies one result series per stream unless grouped.
            // For now, let's SUM them if multiple files match, or just take the first one?
            // Realistically we need to handle aggregations from the stream pipeline.

            // Simple case: Sum all matched files
            $combinedName = "s{$index}_combined";
            if (count($streamDefs) > 1) {
                $expr = implode(',', $streamDefs).str_repeat(',+', count($streamDefs) - 1);
                $cdefs[] = "CDEF:$combinedName=$expr";
            } else {
                $combinedName = $streamDefs[0];
            }

            // Apply pipeline operations (math, etc) - TODO

            // Apply aggregations - TODO

            // Final export
            $legend = $stream->alias ?? $stream->metric;
            // Escape legend
            $legend = str_replace(':', '\:', $legend);

            $xports[] = "XPORT:$combinedName:$legend";
        }

        // If no streams matched any files, we can't generate a valid xport command.
        // We should probably return a dummy query that returns empty result, or throw exception.
        // RrdClient handles RrdNotFoundException, maybe we can throw that?
        // But RrdCompiler shouldn't really check for file existence if possible, but here we did listFilenames.
        if (empty($xports)) {
            // Return a command that does nothing or throws?
            // If we return a command with no arguments, rrdtool will complain "can't make an xport without contents"
            // Let's throw RrdException which RrdClient catches.
            throw new RrdNotFoundException('No RRD files found for query');
        }

        $arguments = [...$defs, ...$cdefs, ...$xports];

        /** @var CompiledQuery<TimeSeriesQueryResult> */
        // @phpstan-ignore-next-line
        return new RrdCommand('xport', $options, $arguments);
    }
}

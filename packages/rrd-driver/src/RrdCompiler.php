<?php

namespace TimeseriesPhp\Driver\RRD;

use InvalidArgumentException;
use TimeseriesPhp\Core\Contracts\CompiledQuery;
use TimeseriesPhp\Core\Contracts\Query;
use TimeseriesPhp\Core\Contracts\QueryCompiler;
use TimeseriesPhp\Core\Contracts\QueryResult;
use TimeseriesPhp\Core\Enum\MathOperator;
use TimeseriesPhp\Core\Query\AST\DataQuery;
use TimeseriesPhp\Core\Query\AST\LabelQuery;
use TimeseriesPhp\Core\Results\LabelQueryResult;
use TimeseriesPhp\Core\Results\TimeSeriesQueryResult;
use TimeseriesPhp\Driver\RRD\Contracts\LabelStrategy;
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
        private LabelStrategy $labelStrategy,
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
            '--json',
        ];

        if ($query->resolution->seconds) {
            $options['--step'] = (string) $query->resolution->seconds;
        }

        $defs = [];
        $cdefs = [];
        $xports = [];

        foreach ($query->streams as $index => $stream) {

            // Find all files matching the filters
            $files = $this->labelStrategy->listFilenames($stream->metric, $stream->filters);

            // Create a DEF for each file
            foreach ($files as $fileIndex => $file) {
                $defName = "s{$index}f{$fileIndex}";

                // Assuming 'value' as the DS name for single-value metrics
                $dsName = 'value';
                $cf = 'AVERAGE';

                $defs[] = "DEF:$defName=$file:$dsName:$cf";

                // Apply pipeline operations to individual file
                $processedName = $this->applyPipeline($defName, $stream->pipeline, $index, $fileIndex, $cdefs);

                // Export individual file series
                $fileLabel = $this->extractLabelFromFile($file, $stream);
                $fileLabel = $this->escapeRrdLabel($fileLabel);
                $xports[] = "XPORT:$processedName:\"$fileLabel\"";
            }
        }

        if (empty($xports)) {
            throw new RrdNotFoundException('No RRD files found for query');
        }

        $arguments = [...$defs, ...$cdefs, ...$xports];

        /** @var CompiledQuery<TimeSeriesQueryResult> */
        // @phpstan-ignore-next-line
        return new RrdCommand('xport', $options, $arguments, $query);
    }

    /**
     * Apply pipeline operations (rate, math, etc.) to a data source
     */
    private function applyPipeline(string $sourceName, array $pipeline, int $streamIndex, int|string $fileIndex, array &$cdefs): string
    {
        $currentName = $sourceName;

        foreach ($pipeline as $opIndex => $operation) {
            $newName = "s{$streamIndex}f{$fileIndex}_op{$opIndex}";

            if ($operation instanceof \TimeseriesPhp\Core\Query\AST\Operations\BasicOperation) {
                // Handle basic operations like rate
                // Note: For COUNTER data sources, RRDtool already calculates the rate automatically
                // so we can skip the rate operation
                $cdef = match ($operation->type->value) {
                    'rate' => null, // Skip - already handled by COUNTER DS type
                    'derivative' => "CDEF:$newName=$currentName,PREV($currentName),-",
                    default => null,
                };

                if ($cdef === null) {
                    // Operation not needed or unknown, skip
                    continue;
                }

                $cdefs[] = $cdef;
                $currentName = $newName;
            } elseif ($operation instanceof \TimeseriesPhp\Core\Query\AST\Operations\MathOperation) {
                // Handle math operations
                $value = $operation->value;

                // Convert operator to RPN
                $cdef = match ($operation->operator) {
                    MathOperator::Add => "CDEF:$newName=$currentName,$value,+",
                    MathOperator::Subtract => "CDEF:$newName=$currentName,$value,-",
                    MathOperator::Multiply => "CDEF:$newName=$currentName,$value,*",
                    MathOperator::Divide => "CDEF:$newName=$currentName,$value,/",
                };

                $cdefs[] = $cdef;
                $currentName = $newName;
            }
        }

        return $currentName;
    }

    /**
     * Extract a meaningful label from the RRD filename
     */
    private function extractLabelFromFile(string $file, $stream): string
    {
        // Extract labels from filename pattern like:
        // network.port/bytes.in/host=amorbis,ifIndex=15,ifName=veth487f2ee_if2.rrd

        $basename = basename($file, '.rrd');

        $prefix = $stream->alias ?? $stream->metric;

        // If the filename contains labels (key=value pairs)
        if (preg_match_all('/(\w+)=([^,]+)/', $basename, $matches, PREG_SET_ORDER)) {
            $labels = [];
            foreach ($matches as $match) {
                $labels[] = $match[1].'='.$match[2];
            }
            $labelStr = implode(',', $labels);

            return $prefix.'{'.$labelStr.'}';
        }

        // Fallback to just the basename
        return $prefix.'{'.$basename.'}';
    }

    /**
     * Escape special characters in RRD labels
     */
    private function escapeRrdLabel(string $label): string
    {
        // Escape colons - quotes will handle spaces
        return str_replace(':', '\:', $label);
    }
}

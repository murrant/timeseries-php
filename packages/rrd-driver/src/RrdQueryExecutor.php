<?php

namespace TimeseriesPhp\Driver\RRD;

use DateTimeImmutable;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use TimeseriesPhp\Core\Contracts\CompiledQuery;
use TimeseriesPhp\Core\Contracts\QueryExecutor;
use TimeseriesPhp\Core\Contracts\QueryResult;
use TimeseriesPhp\Core\Exceptions\TimeseriesException;
use TimeseriesPhp\Core\Query\AST\Resolution;
use TimeseriesPhp\Core\Query\AST\TimeRange;
use TimeseriesPhp\Core\Results\DataPoint;
use TimeseriesPhp\Core\Results\LabelQueryResult;
use TimeseriesPhp\Core\Results\TimeSeries;
use TimeseriesPhp\Core\Results\TimeSeriesQueryResult;
use TimeseriesPhp\Driver\RRD\Exceptions\RrdException;
use TimeseriesPhp\Driver\RRD\Exceptions\RrdNotFoundException;

/**
 * @template TResult of QueryResult
 *
 * @implements QueryExecutor<TResult>
 */
readonly class RrdQueryExecutor implements QueryExecutor
{
    public function __construct(
        private RrdProcess $process, // FIXME wrong interface
        private LoggerInterface $logger = new NullLogger,
    ) {}

    /**
     * @param  CompiledQuery<TResult>  $query
     * @return TResult
     * @throws TimeseriesException
     */
    public function execute(CompiledQuery $query): QueryResult
    {
        if ($query instanceof RrdLabelQuery) {
            /** @var TResult */
            return new LabelQueryResult([], []); // FIXME wire up to LabelStrategy
        }

        if (! $query instanceof RrdCommand) {
            throw new TimeseriesException('RRD client only supports RrdCommand and RrdLabelQuery');
        }

        $this->logger->debug('Executing RRD query', ['query' => (string) $query]);

        try {
            $output = $this->process->run($query);
            // @phpstan-ignore-next-line
            return $this->parseGraphOutput($output);
        } catch (RrdNotFoundException) {
            // @phpstan-ignore-next-line
            return new TimeSeriesQueryResult([], new TimeRange(new DateTimeImmutable, new DateTimeImmutable), new Resolution);
        } catch (RrdException $e) {
            throw new TimeseriesException('RRD execution failed: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * @throws TimeseriesException
     */
    private function parseGraphOutput(string $output): TimeSeriesQueryResult
    {
        $json = json_decode($output, true);
        if (! is_array($json)) {
            throw new TimeseriesException('Failed to parse RRD JSON output');
        }

        $meta = $json['meta'] ?? [];
        $legend = $meta['legend'] ?? [];
        $data = $json['data'] ?? [];
        $start = $meta['start'] ?? 0;
        $end = $meta['end'] ?? 0;
        $step = $meta['step'] ?? 0;

        $seriesData = [];
        foreach ($legend as $index => $label) {
            $seriesData[$index] = [];
        }

        foreach ($data as $rowIndex => $row) {
            $timestamp = $start + ($rowIndex * $step);
            foreach ($row as $colIndex => $value) {
                $val = $value === null ? null : (float) $value;
                $seriesData[$colIndex][] = new DataPoint($timestamp, $val);
            }
        }

        $timeSeries = [];
        foreach ($legend as $index => $legendKey) {
            // We don't have metadata in RrdCommand yet to map back to original metric/labels easily
            // unless we encoded it in the legend.
            // For now, use legendKey as metric name or alias.
            $timeSeries[] = new TimeSeries(
                metric: $legendKey,
                alias: null,
                labels: [],
                points: $seriesData[$index]
            );
        }

        return new TimeSeriesQueryResult(
            series: $timeSeries,
            range: new TimeRange(
                (new DateTimeImmutable)->setTimestamp($start),
                (new DateTimeImmutable)->setTimestamp($end)
            ),
            resolution: new Resolution($step),
        );
    }
}

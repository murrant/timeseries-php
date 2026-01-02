<?php

namespace TimeseriesPhp\Driver\RRD;

use DateTimeImmutable;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use TimeseriesPhp\Core\Contracts\CompiledQuery;
use TimeseriesPhp\Core\Contracts\Result;
use TimeseriesPhp\Core\Contracts\TsdbClient;
use TimeseriesPhp\Core\Exceptions\TimeseriesException;
use TimeseriesPhp\Core\Query\AST\Resolution;
use TimeseriesPhp\Core\Query\AST\TimeRange;
use TimeseriesPhp\Core\Results\DataPoint;
use TimeseriesPhp\Core\Results\LabelResult;
use TimeseriesPhp\Core\Results\TimeSeries;
use TimeseriesPhp\Core\Results\TimeSeriesResult;
use TimeseriesPhp\Driver\RRD\Exceptions\RrdNotFoundException;
use TimeseriesPhp\Driver\RRD\Factories\RrdProcessFactory;

/**
 * @template TResult of Result
 *
 * @implements TsdbClient<TResult>
 */
class RrdClient implements TsdbClient
{
    private readonly RrdProcess $process;

    public function __construct(
        private readonly RrdConfig $config,
        RrdProcessFactory $factory,
        private readonly LoggerInterface $logger = new NullLogger,
    ) {
        $this->process = $factory->make($this->config);
    }

    /**
     * @param  CompiledQuery<TResult>  $query
     * @return TResult
     */
    public function execute(CompiledQuery $query): Result
    {
        if ($query instanceof RrdLabelQuery) {
            /** @var TResult */
            return new LabelResult([], []); // FIXME wire up to LabelStrategy
        }

        if (! $query instanceof RrdCommand) {
            throw new TimeseriesException('RRD client only supports RrdCommand and RrdLabelQuery');
        }

        $this->logger->debug('Executing RRD query', ['query' => (string) $query]);

        $allSeries = [];

        $commonResolution = 0;

        try {
            $output = $this->process->run($query);
            $result = $this->parseGraphOutput($output, $query);

            foreach ($result->series as $series) {
                $allSeries[] = $series;
            }

            $commonResolution = $result->resolution->seconds ?: $commonResolution;
        } catch (RrdNotFoundException) {
            return new TimeSeriesResult([], new TimeRange(new DateTimeImmutable, new DateTimeImmutable), new Resolution);
        }

        if (empty($allSeries)) {
            return new TimeSeriesResult([], new TimeRange(new DateTimeImmutable, new DateTimeImmutable), new Resolution);
        }

        return new TimeSeriesResult(
            series: $allSeries,
            range: $result->range,
            resolution: new Resolution($commonResolution),
        );
    }

    private function parseGraphOutput(string $output, RrdCommand $query): TimeSeriesResult
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
            $streamMeta = $query->metadata['streams'][$legendKey] ?? [];
            $timeSeries[] = new TimeSeries(
                metric: $streamMeta['metric'] ?? 'unknown',
                alias: null,
                labels: $streamMeta['labels'] ?? [],
                points: $seriesData[$index]
            );
        }

        return new TimeSeriesResult(
            series: $timeSeries,
            range: new TimeRange(
                (new DateTimeImmutable)->setTimestamp($start),
                (new DateTimeImmutable)->setTimestamp($end)
            ),
            resolution: new Resolution($step),
        );
    }
}

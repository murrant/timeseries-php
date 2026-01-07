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
use TimeseriesPhp\Core\Results\LabelQueryResult;
use TimeseriesPhp\Core\Results\TimeSeriesQueryResult;
use TimeseriesPhp\Driver\RRD\Exceptions\RrdException;
use TimeseriesPhp\Driver\RRD\Exceptions\RrdNotFoundException;
use TimeseriesPhp\Driver\RRD\Traits\RrdOutputParser;

/**
 * @template TResult of QueryResult
 *
 * @implements QueryExecutor<TResult>
 */
readonly class RrdQueryExecutor implements QueryExecutor
{
    use RrdOutputParser;

    public function __construct(
        private RrdProcess $process, // FIXME wrong interface
        private LoggerInterface $logger = new NullLogger,
    ) {}

    /**
     * @param  CompiledQuery<TResult>  $query
     * @return TResult
     *
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

            return $this->parseXportOutput($output);
        } catch (RrdNotFoundException) {
            return new TimeSeriesQueryResult([], new TimeRange(new DateTimeImmutable, new DateTimeImmutable), new Resolution);
        } catch (RrdException $e) {
            throw new TimeseriesException('RRD execution failed: '.$e->getMessage(), 0, $e);
        }
    }
}

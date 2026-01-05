<?php

declare(strict_types=1);

namespace TimeseriesPhp\Driver\Null;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use TimeseriesPhp\Core\Contracts\CompiledQuery;
use TimeseriesPhp\Core\Contracts\QueryExecutor;
use TimeseriesPhp\Core\Contracts\QueryResult;
use TimeseriesPhp\Core\Query\AST\Resolution;
use TimeseriesPhp\Core\Query\AST\TimeRange;
use TimeseriesPhp\Core\Results\TimeSeriesQueryResult;

final readonly class NullQueryExecutor implements QueryExecutor
{
    public function __construct(
        private LoggerInterface $logger = new NullLogger,
    ) {}

    public function execute(CompiledQuery $query): QueryResult
    {
        $this->logger->debug("Received query execution request for query"); // TODO describe query somehow

        return new TimeSeriesQueryResult([], TimeRange::lastMinutes(60), Resolution::auto());
    }
}

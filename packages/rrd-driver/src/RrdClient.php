<?php

namespace TimeseriesPhp\Driver\RRD;

use DateTimeImmutable;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use TimeseriesPhp\Core\Contracts\CompiledQuery;
use TimeseriesPhp\Core\Contracts\Result;
use TimeseriesPhp\Core\Contracts\TsdbClient;
use TimeseriesPhp\Core\Query\AST\Resolution;
use TimeseriesPhp\Core\Query\AST\TimeRange;
use TimeseriesPhp\Core\Results\TimeSeriesResult;

/**
 * @implements TsdbClient<TimeSeriesResult>
 */
class RrdClient implements TsdbClient
{
    public function __construct(private readonly ?LoggerInterface $logger = new NullLogger) {}

    public function execute(CompiledQuery $query): Result
    {
        $this->logger->debug('Executing RRD query', ['query' => (string) $query]);

        return new TimeSeriesResult([], new TimeRange(new DateTimeImmutable, new DateTimeImmutable), Resolution::minutes(5));
    }
}

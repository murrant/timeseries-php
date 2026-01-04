<?php

namespace TimeseriesPhp\Core\Services;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use TimeseriesPhp\Core\Contracts\Query;
use TimeseriesPhp\Core\Contracts\QueryCompiler;
use TimeseriesPhp\Core\Contracts\QueryExecutor;
use TimeseriesPhp\Core\Contracts\QueryResult;
use TimeseriesPhp\Core\Contracts\TsdbConnection;
use TimeseriesPhp\Core\Contracts\Writer;
use TimeseriesPhp\Core\Metrics\MetricSample;
use TimeseriesPhp\Core\Schema\SchemaManager;

class Connection implements TsdbConnection
{
    /**
     * @template TResult of QueryResult
     *
     * @param  QueryCompiler<TResult>  $compiler
     * @param  QueryExecutor<TResult>  $client
     */
    public function __construct(
        private readonly Writer          $writer,
        private readonly QueryCompiler   $compiler,
        private readonly QueryExecutor   $client,
        private readonly LoggerInterface $logger = new NullLogger,
    ) {}

    public function write(MetricSample $sample): void
    {
        $this->logger->debug('Writing metric sample', ['sample' => $sample]);
        $this->writer->write($sample);
    }

    /**
     * @template TResult of QueryResult
     *
     * @param  Query<TResult>  $query
     * @return QueryResult<TResult>
     */
    public function query(Query $query): QueryResult
    {
        $this->logger->debug('Executing query', ['query' => $query]);

        $result = $this->client->execute($this->compiler->compile($query));

        $this->logger->debug('Query result', ['result' => $result]);

        return $result;
    }

    public function schema(): SchemaManager
    {
        return new SchemaManager($this);
    }
}

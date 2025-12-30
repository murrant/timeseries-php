<?php

namespace TimeseriesPhp\Core;

use TimeseriesPhp\Core\Contracts\Query;
use TimeseriesPhp\Core\Contracts\QueryCompiler;
use TimeseriesPhp\Core\Contracts\Result;
use TimeseriesPhp\Core\Contracts\TsdbClient;
use TimeseriesPhp\Core\Contracts\TsdbConnection;
use TimeseriesPhp\Core\Contracts\TsdbWriter;
use TimeseriesPhp\Core\Metrics\MetricSample;
use TimeseriesPhp\Core\Schema\SchemaManager;

class Connection implements TsdbConnection
{
    /**
     * @template TResult of Result
     *
     * @param  QueryCompiler<TResult>  $compiler
     * @param  TsdbClient<TResult>  $client
     */
    public function __construct(
        private readonly TsdbWriter $writer,
        private readonly QueryCompiler $compiler,
        private readonly TsdbClient $client,
    ) {}

    public function write(MetricSample $sample): void
    {
        $this->writer->write($sample);
    }

    /**
     * @template TResult of Result
     *
     * @param  Query<TResult>  $query
     * @return Result<TResult>
     */
    public function query(Query $query): Result
    {
        $result = $this->client->execute($this->compiler->compile($query));
        //
        //        dump($query,$result);

        return $result;
    }

    public function schema(): SchemaManager
    {
        return new SchemaManager($this);
    }
}

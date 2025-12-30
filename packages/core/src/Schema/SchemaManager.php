<?php

namespace TimeseriesPhp\Core\Schema;

use TimeseriesPhp\Core\Contracts\TsdbConnection;
use TimeseriesPhp\Core\Query\Labels\LabelQueryBuilder;

readonly class SchemaManager
{
    public function __construct(
        private TsdbConnection $connection
    ) {}

    public function labels(): LabelQueryBuilder
    {
        return new LabelQueryBuilder($this->connection);
    }

    public function metricExists(string $metric): bool
    {
        return false; // TODO
    }
}

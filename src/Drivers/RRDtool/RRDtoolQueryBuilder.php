<?php

namespace TimeSeriesPhp\Drivers\RRDtool;

use TimeSeriesPhp\Core\Query;
use TimeSeriesPhp\Core\QueryBuilderContract;
use TimeSeriesPhp\Core\RawQueryContract;
use TimeSeriesPhp\Drivers\RRDtool\Tags\RRDTagStrategyContract;

class RRDtoolQueryBuilder implements QueryBuilderContract
{
    private RRDTagStrategyContract $tagStrategy;
    private string $rrdDir;

    public function __construct(RRDTagStrategyContract $tagStrategy, string $rrdDir)
    {
        $this->tagStrategy = $tagStrategy;
        $this->rrdDir = $rrdDir;
    }

    public function build(Query $query): RawQueryContract
    {
        $rawQuery = new RRDtoolRawQuery();

        // create rrdtool command

        return $rawQuery;
    }

    private function mapAggregationToConsolidationFunction(?string $aggregation): string
    {
        $mapping = [
            'avg' => 'AVERAGE',
            'mean' => 'AVERAGE',
            'average' => 'AVERAGE',
            'max' => 'MAX',
            'min' => 'MIN',
            'last' => 'LAST'
        ];

        return $mapping[strtolower($aggregation ?? 'average')] ?? 'AVERAGE';
    }

    private function getRRDPath(string $measurement, array $tags = []): string
    {
        return $this->tagStrategy->getFilePath($measurement, $tags, $this->rrdDir);
    }
}

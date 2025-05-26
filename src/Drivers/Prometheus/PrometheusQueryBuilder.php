<?php

namespace TimeSeriesPhp\Drivers\Prometheus;

use TimeSeriesPhp\Core\Query;
use TimeSeriesPhp\Core\QueryBuilderContract;
use TimeSeriesPhp\Core\RawQuery;
use TimeSeriesPhp\Core\RawQueryContract;

class PrometheusQueryBuilder implements QueryBuilderContract
{
    public function build(Query $query): RawQueryContract
    {
        // Prometheus uses PromQL
        $metric = $query->getMeasurement();
        $filters = [];

        foreach ($query->getTags() as $label => $value) {
            $filters[] = "{$label}=\"{$value}\"";
        }

        $filterStr = empty($filters) ? '' : '{' . implode(',', $filters) . '}';

        if ($query->getAggregation()) {
            return new RawQuery("{$query->getAggregation()}({$metric}{$filterStr})");
        }

        return new RawQuery($metric . $filterStr);
    }
}

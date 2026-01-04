<?php

namespace TimeseriesPhp\Core\Query\Data;

use TimeseriesPhp\Core\Contracts\Query;
use TimeseriesPhp\Core\Query\AST\DataQuery;
use TimeseriesPhp\Core\Query\AST\Resolution;
use TimeseriesPhp\Core\Query\AST\Stream;
use TimeseriesPhp\Core\Query\AST\TimeRange;
use TimeseriesPhp\Core\Results\TimeSeriesQueryResult;

class QueryBuilder
{
    private readonly TimeRange $period;

    private Resolution $resolution;

    /** @var Stream[] */
    private array $streams = [];

    public function __construct(
        ?TimeRange $period = null,
    ) {
        $this->period = $period ?? TimeRange::lastDays(30); // FIXME default??
        $this->resolution = Resolution::auto();
    }

    public static function for(TimeRange $period): self
    {
        return new self($period);
    }

    public function resolution(Resolution $res): self
    {
        $this->resolution = $res;

        return $this;
    }

    public function select(string $metric, callable $setup): self
    {
        $builder = new StreamBuilder($metric);
        $setup($builder);
        $this->streams[] = $builder->build();

        return $this;
    }

    /**
     * @return Query<TimeSeriesQueryResult>
     */
    public function build(): DataQuery
    {
        return new DataQuery(
            period: $this->period,
            resolution: $this->resolution,
            streams: $this->streams,
        );
    }
}

<?php

namespace TimeseriesPhp\Driver\InfluxDB2;

use TimeseriesPhp\Core\Contracts\LabelDiscovery;
use TimeseriesPhp\Core\Contracts\QueryCompiler;
use TimeseriesPhp\Core\Contracts\QueryExecutor;
use TimeseriesPhp\Core\Query\AST\LabelQuery;
use TimeseriesPhp\Core\Results\LabelQueryResult;

class InfluxLabelDiscovery implements LabelDiscovery
{
    public function __construct(
        private readonly QueryCompiler $compiler,
        private readonly QueryExecutor $executor,
    ) {}

    public function listLabels(array $metrics, array $filters = []): array
    {
        // TODO implement directly
        $query = new LabelQuery(null, $metrics, $filters, null);
        $compiled = $this->compiler->compile($query);

        /** @var LabelQueryResult $result */
        $result = $this->executor->execute($compiled);

        return $result->labels;
    }

    public function listLabelValues(string $label, array $metrics, array $filters = []): array
    {
        // TODO implement directly
        $query = new LabelQuery($label, $metrics, $filters, null);
        $compiled = $this->compiler->compile($query);

        /** @var LabelQueryResult $result */
        $result = $this->executor->execute($compiled);

        return $result->values;
    }
}

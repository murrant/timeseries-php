<?php

namespace TimeseriesPhp\Core\Contracts;

use TimeseriesPhp\Core\Query\AST\Filter;

interface LabelDiscovery
{
    /**
     * List labels (tags) available for the given metrics.
     *
     * @param  list<string>  $metrics
     * @param  Filter[]  $filters
     * @return list<string>
     */
    public function listLabels(array $metrics, array $filters = []): array;

    /**
     * List possible values for a label, given metrics and filters.
     *
     * @param  list<string>  $metrics
     * @param  Filter[]  $filters
     * @return list<scalar>
     */
    public function listLabelValues(string $label, array $metrics, array $filters = []): array;
}

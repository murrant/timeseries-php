<?php

namespace TimeseriesPhp\Driver\RRD\Contracts;

use TimeseriesPhp\Core\Metrics\MetricIdentifier;
use TimeseriesPhp\Core\Query\AST\Filter;
use TimeseriesPhp\Driver\RRD\Exceptions\RrdException;

interface LabelStrategy
{
    /**
     * Generate filename for creating/writing RRD files with all available labels.
     *
     * @param  array<string, string>  $labels  Key-value pairs of label dimensions
     * @return string Relative path from baseDir
     *
     * @throws RrdException If an undefined label is provided
     */
    public function generateFilename(MetricIdentifier $metric, array $labels): string;

    /**
     * List RRD filenames for a given metric, optionally filtered by labels.
     *
     * @param  Filter[]  $filters  Optional label filters
     * @return string[] Array of relative file paths
     */
    public function listFilenames(MetricIdentifier $metric, array $filters = []): array;

    /**
     * List all label names available for a specific metric.
     *
     * @param  MetricIdentifier|MetricIdentifier[]  $metrics
     * @return string[] Unique label names found across all files
     */
    public function listLabelNames(MetricIdentifier|array $metrics): array;

    /**
     * List label values for a given label name and query (metric + filters).
     *
     * @param  MetricIdentifier|MetricIdentifier[]  $metrics
     * @param  string  $labelName  The label to get values for
     * @param  Filter[]  $filters  Additional filters to narrow results
     * @return string[] Unique values for the specified label
     */
    public function listLabelValues(MetricIdentifier|array $metrics, string $labelName, array $filters = []): array;
}

<?php

namespace TimeseriesPhp\Driver\RRD\Contracts;

use TimeseriesPhp\Core\Contracts\LabelDiscovery;
use TimeseriesPhp\Core\Metrics\MetricIdentifier;
use TimeseriesPhp\Core\Query\AST\Filter;
use TimeseriesPhp\Driver\RRD\Exceptions\RrdException;

interface LabelStrategy extends LabelDiscovery
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
    public function listFilenames(string $metric, array $filters = []): array;

    /**
     * Extract labels from a given rrd file path
     * @return  array<string, string>  Key-value pairs of label dimensions
     */
    public function labelsFromFilename(string $path): array;
}

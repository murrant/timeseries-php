<?php

namespace TimeSeriesPhp\Drivers\RRDtool\Tags;

use TimeSeriesPhp\Exceptions\RRDtoolTagException;

interface RRDTagStrategyInterface
{
    /**
     * @throws RRDtoolTagException
     */
    public function __construct(string $baseDir);

    public function getBaseDir(): string;

    /**
     * Convert a measurement name and tags to a file path
     *
     * @param  string  $measurement  The measurement name
     * @param  array<string, string>  $tags  The tags as key-value pairs
     * @return string The full path to the RRD file
     *
     * @throws RRDtoolTagException
     */
    public function getFilePath(string $measurement, array $tags = []): string;

    /**
     * Find all measurements that match all tag conditions
     *
     * @param  TagCondition[]  $tagConditions
     * @return string[]
     *
     * @throws RRDtoolTagException
     */
    public function findMeasurementsByTags(array $tagConditions): array;

    /**
     * Find files that match one or more tag values
     *
     * @param  TagCondition[]  $tagConditions  Tag conditions
     * @return string[] List of file paths that match all the tags
     *
     * @throws RRDtoolTagException
     */
    public function resolveFilePaths(string $measurement, array $tagConditions): array;
}

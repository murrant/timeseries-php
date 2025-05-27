<?php

namespace TimeSeriesPhp\Drivers\RRDtool\Tags;

interface RRDTagStrategyContract
{
    public function __construct(string $baseDir);

    public function getBaseDir(): string;

    /**
     * Convert a measurement name and tags to a file path
     *
     * @param  string  $measurement  The measurement name
     * @param  array<string, string>  $tags  The tags as key-value pairs
     * @return string The full path to the RRD file
     */
    public function getFilePath(string $measurement, array $tags = []): string;

    /**
     * Find all measurements that match all tag conditions
     *
     * @param  TagCondition[]  $tagConditions
     * @return string[]
     */
    public function findMeasurementsByTags(array $tagConditions): array;

    /**
     * Find files that match one or more tag values
     *
     * @param  TagCondition[]  $tagConditions  Tag conditions
     * @return string[] List of file paths that match all the tags
     */
    public function resolveFilePaths(string $measurement, array $tagConditions): array;
}

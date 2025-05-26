<?php


namespace TimeSeriesPhp\Drivers\RRDtool\Tags;

interface RRDTagStrategyContract
{
    /**
     * Convert a measurement name and tags to a file path
     *
     * @param string $measurement The measurement name
     * @param array $tags The tags as key-value pairs
     * @param string $baseDir The base directory for RRD files
     * @return string The full path to the RRD file
     */
    public function getFilePath(string $measurement, array $tags, string $baseDir): string;

    /**
     * Find files that have a specific tag value
     *
     * @param string $tagName The tag name to search for
     * @param string $tagValue The tag value to search for
     * @param string $baseDir The base directory for RRD files
     * @return array List of file paths that match the tag
     */
    public function findFilesByTag(string $tagName, string $tagValue, string $baseDir): array;

    /**
     * Find files that match a set of tag values
     *
     * @param array $tags The tags as key-value pairs to search for
     * @param string $baseDir The base directory for RRD files
     * @return array List of file paths that match all the tags
     */
    public function findFilesByTags(array $tags, string $baseDir): array;

    /**
     * Get the configuration for this strategy
     *
     * @return array The configuration
     */
    public function getConfig(): array;

    /**
     * Set the configuration for this strategy
     *
     * @param array $config The configuration
     * @return void
     */
    public function setConfig(array $config): void;
}

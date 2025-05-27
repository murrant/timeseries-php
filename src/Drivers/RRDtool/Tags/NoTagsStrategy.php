<?php

namespace TimeSeriesPhp\Drivers\RRDtool\Tags;

class NoTagsStrategy implements RRDTagStrategyContract
{
    /**
     * @var array Configuration for the strategy
     */
    private array $config = [];

    /**
     * @inheritDoc
     */
    public function getFilePath(string $measurement, array $tags, string $baseDir): string
    {
        // Ignore tags, use only measurement name
        $filename = preg_replace('/[^a-zA-Z0-9_-]/', '_', $measurement);
        return $baseDir . '/' . $filename . '.rrd';
    }

    /**
     * @inheritDoc
     */
    public function findFilesByTags(array $tags, string $baseDir): array
    {
        // This strategy doesn't use tags, so it can't find files by tags
        return [];
    }

    /**
     * @inheritDoc
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * @inheritDoc
     */
    public function setConfig(array $config): void
    {
        $this->config = array_merge($this->config, $config);
    }
}

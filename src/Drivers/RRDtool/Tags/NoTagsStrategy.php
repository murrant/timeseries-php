<?php

namespace TimeSeriesPhp\Drivers\RRDtool\Tags;

use TimeSeriesPhp\Drivers\RRDtool\Exception\RRDtoolTagException;
use TimeSeriesPhp\Utils\File;

class NoTagsStrategy implements RRDTagStrategyInterface
{
    protected string $folderSeparator = '/';

    /**
     * @throws RRDtoolTagException
     */
    public function __construct(
        public readonly string $baseDir
    ) {
        if (! str_ends_with($this->baseDir, $this->folderSeparator)) {
            throw new RRDtoolTagException('Base directory must end with a slash');
        }
    }

    public function getBaseDir(): string
    {
        return $this->baseDir;
    }

    /**
     * {@inheritDoc}
     */
    public function getFilePath(string $measurement, array $tags = []): string
    {
        // Ignore tags, use only measurement name
        $measurement = File::sanitize($measurement);

        return $this->baseDir.$measurement.'.rrd';
    }

    public function findMeasurementsByTags(array $tagConditions): array
    {
        return array_map(fn ($file) => basename($file, '.rrd'), glob($this->baseDir.'*.rrd') ?: []);
    }

    public function resolveFilePaths(string $measurement, array $tagConditions): array
    {
        $measurement = File::sanitize($measurement);

        return glob($this->baseDir.$measurement.'*.rrd') ?: [];
    }
}

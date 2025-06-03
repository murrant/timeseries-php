<?php

namespace TimeSeriesPhp\Drivers\RRDtool\Tags;

use TimeSeriesPhp\Drivers\RRDtool\Exception\RRDtoolTagException;
use TimeSeriesPhp\Drivers\RRDtool\RRDtoolConfig;
use TimeSeriesPhp\Utils\File;

class NoTagsStrategy implements RRDTagStrategyInterface
{
    /**
     * @throws RRDtoolTagException
     */
    public function __construct(
        public readonly RRDtoolConfig $config,
    ) {
        if (! str_ends_with($this->getBaseDir(), DIRECTORY_SEPARATOR)) {
            throw new RRDtoolTagException('Base directory must end with a slash');
        }
    }

    public function getBaseDir(): string
    {
        return $this->config->rrd_dir;
    }

    /**
     * {@inheritDoc}
     */
    public function getFilePath(string $measurement, array $tags = []): string
    {
        // Ignore tags, use only measurement name
        $measurement = File::sanitize($measurement);

        return $this->getBaseDir().$measurement.'.rrd';
    }

    public function findMeasurementsByTags(array $tagConditions): array
    {
        return array_map(fn ($file) => basename($file, '.rrd'), glob($this->getBaseDir().'*.rrd') ?: []);
    }

    public function resolveFilePaths(string $measurement, array $tagConditions): array
    {
        $measurement = File::sanitize($measurement);

        return glob($this->getBaseDir().$measurement.'*.rrd') ?: [];
    }
}

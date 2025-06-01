<?php

namespace TimeSeriesPhp\Drivers\RRDtool\Tags;

use SplFileInfo;
use TimeSeriesPhp\Drivers\RRDtool\Exception\RRDtoolFilenameTooLongException;
use TimeSeriesPhp\Drivers\RRDtool\Exception\RRDtoolTagException;

class FileNameStrategy implements RRDTagStrategyInterface
{
    use EncodesTagsInFilename;

    public function __construct(
        public readonly string $baseDir
    ) {
        if (! str_ends_with($this->baseDir, DIRECTORY_SEPARATOR)) {
            throw new RRDtoolTagException('Base directory must end with a slash');
        }
    }

    public function getBaseDir(): string
    {
        return $this->baseDir;
    }

    /**
     * {@inheritDoc}
     *
     * @throws RRDtoolFilenameTooLongException
     */
    public function getFilePath(string $measurement, array $tags = []): string
    {
        $filename = $this->encodeTags($measurement, $tags);

        return $this->baseDir.$filename;
    }

    public function resolveFilePaths(string $measurement, array $tagConditions): array
    {
        if (empty($tagConditions)) {
            return glob($this->baseDir.$measurement.'*.rrd') ?: [];
        }

        $files = [];
        $dirIterator = new \RecursiveDirectoryIterator($this->baseDir);
        $iterator = new \RecursiveIteratorIterator($dirIterator);

        foreach ($iterator as $file) {
            /** @var SplFileInfo $file */
            if ($file->isFile() && $file->getExtension() === 'rrd') {
                $filename = $file->getBasename('.rrd');
                if (! str_starts_with($filename, $measurement)) {
                    continue; // wrong measurement
                }

                $tags = $this->parseTags($filename);

                if (TagSearch::search($tags, $tagConditions)) {
                    $files[] = $file->getPathname();
                }
            }
        }

        return $files;
    }

    public function findMeasurementsByTags(array $tagConditions): array
    {
        $files = $this->resolveFilePaths('', $tagConditions);

        return $this->parseMeasurements($files);
    }
}

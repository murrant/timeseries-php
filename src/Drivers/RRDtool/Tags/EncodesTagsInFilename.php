<?php

namespace TimeSeriesPhp\Drivers\RRDtool\Tags;

use TimeSeriesPhp\Utils\File;

trait EncodesTagsInFilename
{
    protected function encodeTags(string $measurement, array $tags, string $tagValueSeparator = '-', string $tagSeparator = '_'): string
    {
        $filename = $measurement;

        if (! empty($tags)) {
            ksort($tags); // Ensure consistent naming
            $tagStr = implode($tagSeparator, array_map(function($k, $v) use ($tagValueSeparator) {
                return "{$k}{$tagValueSeparator}{$v}";
            }, array_keys($tags), array_values($tags)));
            $filename .= $tagSeparator . $tagStr;
        }

        return File::sanitize($filename . '.rrd');
    }

    protected function parseTags(string $filename): array
    {
        preg_match_all("/(\\w+)$this->tagSeparator(\\w+)/", $filename, $matches);

        return array_combine($matches[1], $matches[2]);
    }

    protected function parseMeasurements(array $filenames): array
    {
        return array_unique(array_map(function ($filename) {
            $basename = basename($filename, '.rrd');
            $parts = explode($this->filenameSeparator, $basename);
            return $parts[0] ?? $basename;
        }, $filenames));
    }
}

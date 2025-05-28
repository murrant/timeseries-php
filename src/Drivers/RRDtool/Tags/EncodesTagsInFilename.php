<?php

namespace TimeSeriesPhp\Drivers\RRDtool\Tags;

use TimeSeriesPhp\Exceptions\RRDtoolFilenameTooLongException;
use TimeSeriesPhp\Utils\File;

trait EncodesTagsInFilename
{
    /**
     * @param  array<string, string>  $tags
     *
     * @throws RRDtoolFilenameTooLongException
     */
    protected function encodeTags(string $measurement, array $tags): string
    {
        $filename = $measurement;

        if (! empty($tags)) {
            ksort($tags); // Ensure consistent naming
            $tagStr = implode(File::TAG_SEPARATOR, array_map(function ($k, $v) {
                $k = File::sanitizeTag($k);
                $v = File::sanitizeTag($v);

                return $k.File::TAG_VALUE_SEPARATOR.$v;
            }, array_keys($tags), array_values($tags)));
            $filename .= File::TAG_SEPARATOR.$tagStr;
        }

        $filename = File::sanitize($filename.'.rrd');

        if (strlen($filename) > 255) {
            throw new RRDtoolFilenameTooLongException("RRDtool filename too long: $filename");
        }

        return $filename;
    }

    /**
     * @return array<string, string>
     */
    protected function parseTags(string $filename): array
    {
        // should no contain suffix
        $tagChars = '([^'.File::TAG_SEPARATOR.File::TAG_VALUE_SEPARATOR.']+)';
        $regex = '#'.$tagChars.File::TAG_VALUE_SEPARATOR.$tagChars.'#';
        preg_match_all($regex, $filename, $matches);

        return array_combine($matches[1], $matches[2]);
    }

    /**
     * @param  string[]  $filenames
     * @return string[]
     */
    protected function parseMeasurements(array $filenames): array
    {
        return array_unique(array_map(function ($filename) {
            $basename = basename($filename, '.rrd');
            $part = substr($basename, 0, strpos($basename, File::TAG_VALUE_SEPARATOR));

            return substr($part, 0, strrpos($part, File::TAG_SEPARATOR));
        }, $filenames));
    }
}

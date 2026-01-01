<?php

namespace TimeseriesPhp\Driver\RRD;

trait RrdCommandBuilder
{
    private function buildListCommand(string $directory, bool $recursive = false): RrdCommand
    {
        $params = $recursive ? ['--recursive'] : [];

        if ($this->config->rrdcached) {
            $directory = str_replace($this->config->dir, '/', $directory);
        }

        return new RrdCommand('list', $params, [$directory]);
    }
}

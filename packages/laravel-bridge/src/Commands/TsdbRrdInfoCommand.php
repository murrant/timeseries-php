<?php

namespace TimeseriesPhp\Bridge\Laravel\Commands;

use Illuminate\Console\Command;
use TimeseriesPhp\Core\Exceptions\TimeseriesException;
use TimeseriesPhp\Core\TimeseriesManager;
use TimeseriesPhp\Driver\RRD\Factories\RrdtoolFactory;
use TimeseriesPhp\Driver\RRD\RrdConfig;

class TsdbRrdInfoCommand extends Command
{
    protected $signature = 'tsdb:rrd-info {path} {--c|connection=}';

    protected $description = 'Get info about an RRDtool file'; // should probably accept metric and labels?

    public function handle(TimeseriesManager $tsm, RrdtoolFactory $factory): int
    {
        $path = $this->argument('path');
        $connection = $this->option('connection');

        try {
            $rrdConfig = $tsm->connection($connection)->config;
        } catch (TimeseriesException $e) {
            $this->error($e->getMessage());

            return 1;
        }

        if (! $rrdConfig instanceof RrdConfig) {
            $this->error('Connection does not use rrd driver, specify a valid connection');

            return 1;
        }

        $rrd = $factory->make($rrdConfig);

        $result = $rrd->info($path);

        /* -------------------------------------------------
         * Header
         * ------------------------------------------------- */
        $this->info('RRD Info');
        $this->line(sprintf('  File:        %s', $result['filename']));
        $this->line(sprintf('  Version:     %s', $result['rrd_version']));
        $this->line(sprintf('  Step:        %d', $result['step']));
        $this->line(sprintf('  Last update: %d', $result['last_update']));
        $this->line(sprintf('  Header size: %d', $result['header_size']));

        /* -------------------------------------------------
         * Data Sources
         * ------------------------------------------------- */
        $this->newLine();
        $this->info('Data Sources');

        foreach ($result['ds'] as $name => $ds) {
            $this->line("  {$name}:");

            foreach ($ds as $key => $value) {
                $this->line(sprintf(
                    '    %-20s %s',
                    $key,
                    is_float($value) ? sprintf('%g', $value) : (string) $value
                ));
            }
        }

        /* -------------------------------------------------
         * RRAs
         * ------------------------------------------------- */
        $this->newLine();
        $this->info('Round Robin Archives');

        foreach ($result['rra'] as $i => $rra) {
            $this->line("  RRA {$i}:");

            foreach ($rra as $key => $value) {
                if ($key === 'cdp_prep') {
                    continue;
                }

                $this->line(sprintf(
                    '    %-20s %s',
                    $key,
                    is_float($value) ? sprintf('%g', $value) : (string) $value
                ));
            }

            if (!empty($rra['cdp_prep'])) {
                foreach ($rra['cdp_prep'] as $j => $cdp) {
                    $this->line("    cdp_prep[{$j}]:");

                    foreach ($cdp as $k => $v) {
                        $this->line(sprintf(
                            '      %-18s %s',
                            $k,
                            is_float($v) ? sprintf('%g', $v) : (string) $v
                        ));
                    }
                }
            }
        }

        return 0;
    }
}

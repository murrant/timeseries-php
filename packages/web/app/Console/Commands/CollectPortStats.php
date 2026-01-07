<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use TimeseriesPhp\Core\Contracts\MetricRepository;
use TimeseriesPhp\Core\Metrics\MetricSample;
use TimeseriesPhp\Core\TimeseriesManager;

class CollectPortStats extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:collect-port-stats';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Collect stats from localhost and write to TSDB';

    /**
     * Execute the console command.
     */
    public function handle(TimeSeriesManager $manager, MetricRepository $metricRepository): void
    {
        $durations = [];
        $command_start = microtime(true);
        $output = $this->executeCommand();
        $durations[] = ['op' => 'command', 'time' => microtime(true) - $command_start];

        $start = microtime(true);
        $interfaces = $this->parseOutput($output);
        $durations[] = ['op' => 'parse', 'time' => microtime(true) - $start];

        $start = microtime(true);
        $metrics = $this->convertToMetrics($interfaces, $metricRepository);
        $durations[] = ['op' => 'convert', 'time' => microtime(true) - $start];

        $start = microtime(true);
        $writeConnection = config('timeseries.default_write', config('timeseries.default')); // FIXME handle inside manager
        $manager->connection($writeConnection)->writer()->writeBatch($metrics);
        $durations[] = ['op' => 'write', 'time' => microtime(true) - $start];

        if ($this->getOutput()->isVerbose()) {
            $durations[] = ['op' => 'total', 'time' => microtime(true) - $command_start];

            $this->table(['op', 'time'], $durations);
        }
    }

    private function executeCommand(): string
    {
        $command = 'ip -s link 2>&1';
        $output = shell_exec($command);

        if ($output === null || $output === '') {
            throw new \RuntimeException('Failed to execute ip -s link command');
        }

        return $output;
    }

    /**
     * @return array<string, array<string, int>>
     */
    private function parseOutput(string $output): array
    {
        $lines = explode("\n", trim($output));
        $interfaces = [];
        $currentInterface = null;
        $statsSection = 0;

        foreach ($lines as $line) {
            $line = trim($line);

            if (empty($line)) {
                continue;
            }

            // Interface header line (starts with number)
            if (preg_match('/^(\d+):\s+([^:]+):/', $line, $matches)) {
                $ifIndex = $matches[1];
                $ifName = $matches[2];

                $currentInterface = "$ifIndex.$ifName";
                $interfaces[$currentInterface] = [];
                $statsSection = 0;

                continue;
            }

            // RX (receive) stats line
            if ($currentInterface !== null && preg_match('/^RX:\s+(.+)$/', $line, $matches)) {
                $statsSection = 1;

                continue;
            }

            // TX (transmit) stats line
            if ($currentInterface !== null && preg_match('/^TX:\s+(.+)$/', $line, $matches)) {
                $statsSection = 2;

                continue;
            }

            // Parse actual statistics (lines with numbers)
            if ($currentInterface !== null && preg_match('/^\s*(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)/', $line, $matches)) {
                if ($statsSection === 1) {
                    // RX stats
                    $interfaces[$currentInterface]['network.port.bytes.in'] = (int) $matches[1];
                    $interfaces[$currentInterface]['network.port.packets.in'] = (int) $matches[2];
                    $interfaces[$currentInterface]['network.port.errors.in'] = (int) $matches[3];
                    $interfaces[$currentInterface]['network.port.dropped.in'] = (int) $matches[4];
                    $interfaces[$currentInterface]['network.port.overrun'] = (int) $matches[5];
                    $interfaces[$currentInterface]['network.port.multicast'] = (int) $matches[6];
                } elseif ($statsSection === 2) {
                    // TX stats
                    $interfaces[$currentInterface]['network.port.bytes.out'] = (int) $matches[1];
                    $interfaces[$currentInterface]['network.port.packets.out'] = (int) $matches[2];
                    $interfaces[$currentInterface]['network.port.errors.out'] = (int) $matches[3];
                    $interfaces[$currentInterface]['network.port.dropped.out'] = (int) $matches[4];
                    $interfaces[$currentInterface]['network.port.carrier'] = (int) $matches[5];
                    $interfaces[$currentInterface]['network.port.collisions'] = (int) $matches[6];
                }
            }
        }

        return $interfaces;
    }

    /**
     * @param  array<string, array<string, int>>  $interfaces
     * @return MetricSample[]
     */
    private function convertToMetrics(array $interfaces, MetricRepository $metricRepository): array
    {
        $host = gethostname() ?: 'localhost';
        $metrics = [];
        $timestamp = new \DateTimeImmutable;

        foreach ($interfaces as $interface => $stats) {
            [$ifIndex, $ifName] = explode('.', $interface, 2);

            foreach ($stats as $statName => $value) {
                $metricId = $metricRepository->get($statName);

                $metrics[] = new MetricSample(
                    metric: $metricId,
                    labels: [
                        'host' => $host,
                        'ifName' => $ifName,
                        'ifIndex' => $ifIndex,
                    ],
                    value: $value,
                    timestamp: $timestamp
                );
            }
        }

        return $metrics;
    }
}

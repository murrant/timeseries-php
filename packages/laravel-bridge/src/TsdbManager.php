<?php

namespace TimeseriesPhp\Bridge\Laravel;

use Illuminate\Support\Manager;
use InvalidArgumentException;
use TimeseriesPhp\Core\Attributes\TsdbDriver;
use TimeseriesPhp\Core\Connection;
use TimeseriesPhp\Core\Contracts\TsdbConfig;
use TimeseriesPhp\Core\Contracts\TsdbConnection;
use TimeseriesPhp\Core\DriverResolver;

class TsdbManager extends Manager
{
    /** @var TsdbDriver[]|null */
    protected ?array $manifest = null;

    public function getDefaultDriver()
    {
        return $this->config->get('timeseries.default', 'runtime');
    }

    #[\Override]
    protected function createDriver($driver): TsdbConnection
    {
        $configData = $this->config->get("timeseries.connections.{$driver}");

        if (! $configData) {
            throw new InvalidArgumentException("TSDB connection [{$driver}] not configured.");
        }

        $driverName = $configData['driver'];
        $metadata = $this->resolveMetadata($driverName);

        $config = $this->loadConfig($metadata->config, $configData);

        $writer = app($metadata->writer, ['config' => $config]);
        $compiler = app($metadata->compiler, ['config' => $config]);
        $client = app($metadata->client, ['config' => $config]);

        // Instantiate the Connection class
        return new Connection($writer, $compiler, $client);
    }

    public function loadConfig(string $class, array $data): TsdbConfig
    {
        unset($data['driver']); // FIXME elegance

        return $class::fromArray($data);
    }

    public function resolveMetadata(string $name): TsdbDriver
    {
        if ($this->manifest === null) {
            $path = $this->container->bootstrapPath('cache/timeseries_drivers.php');

            // Load from bootstrap/cache if exists, otherwise live scan
            $this->manifest = file_exists($path)
                ? require $path
                : DriverResolver::resolveAll(); // live discovery
        }

        if (! isset($this->manifest[$name])) {
            throw new InvalidArgumentException("Driver [{$name}] not found in manifest.");
        }

        return $this->manifest[$name];
    }
}

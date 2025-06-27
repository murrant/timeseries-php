<?php

namespace TimeSeriesPhp\Drivers\RRDtool;

use Psr\Log\LoggerInterface;
use TimeSeriesPhp\Contracts\Connection\ConnectionAdapterInterface;
use TimeSeriesPhp\Contracts\Driver\ConfigurableInterface;
use TimeSeriesPhp\Contracts\Query\RawQueryInterface;
use TimeSeriesPhp\Core\Attributes\Driver;
use TimeSeriesPhp\Core\Data\DataPoint;
use TimeSeriesPhp\Core\Data\QueryResult;
use TimeSeriesPhp\Core\Driver\AbstractTimeSeriesDB;
use TimeSeriesPhp\Drivers\RRDtool\Connection\LocalConnectionAdapter;
use TimeSeriesPhp\Drivers\RRDtool\Connection\PersistentProcessConnectionAdapter;
use TimeSeriesPhp\Drivers\RRDtool\Connection\RRDCachedConnectionAdapter;
use TimeSeriesPhp\Drivers\RRDtool\Exception\RRDtoolException;
use TimeSeriesPhp\Drivers\RRDtool\Exception\RRDtoolPrematureUpdateException;
use TimeSeriesPhp\Drivers\RRDtool\Factory\ProcessFactoryInterface;
use TimeSeriesPhp\Drivers\RRDtool\Tags\RRDTagStrategyInterface;
use TimeSeriesPhp\Exceptions\Driver\ConnectionException;
use TimeSeriesPhp\Exceptions\Driver\DriverException;
use TimeSeriesPhp\Exceptions\Driver\WriteException;
use TimeSeriesPhp\Exceptions\Query\RawQueryException;

#[Driver(name: 'rrdtool', queryBuilderClass: RRDtoolQueryBuilder::class, configClass: RRDtoolConfig::class)]
class RRDtoolDriver extends AbstractTimeSeriesDB implements ConfigurableInterface
{
    protected bool $connected = false;

    protected RRDtoolQueryBuilder $rrdQueryBuilder;

    protected ConnectionAdapterInterface $connectionAdapter;

    public function __construct(
        protected RRDtoolConfig $config,
        protected ProcessFactoryInterface $processFactory,
        protected RRDTagStrategyInterface $tagStrategy,
        RRDtoolQueryBuilder $queryBuilder,
        LoggerInterface $logger,
        ?ConnectionAdapterInterface $connectionAdapter = null,
    ) {
        parent::__construct($queryBuilder, $logger);

        $this->rrdQueryBuilder = $queryBuilder;

        // Create the appropriate connection adapter if not provided
        if ($connectionAdapter === null) {
            $this->connectionAdapter = $this->createConnectionAdapter();
        } else {
            $this->connectionAdapter = $connectionAdapter;
        }
    }

    /**
     * Create a connection adapter based on the configuration
     */
    protected function createConnectionAdapter(): ConnectionAdapterInterface
    {
        // If rrdcached is enabled, use the appropriate adapter
        if ($this->config->rrdcached_enabled) {
            if ($this->config->persistent_process) {
                // Use persistent process adapter with rrdcached
                return new PersistentProcessConnectionAdapter(
                    $this->config,
                    $this->processFactory,
                    $this->logger
                );
            } else {
                // Use direct connection to rrdcached
                return new RRDCachedConnectionAdapter(
                    $this->config,
                    $this->processFactory,
                    $this->logger
                );
            }
        }

        // Default to local connection adapter
        return new LocalConnectionAdapter(
            $this->config,
            $this->processFactory,
            $this->logger
        );
    }

    /**
     * Configure the driver with the given configuration
     *
     * @param  array<string, mixed>  $config
     */
    public function configure(array $config): void
    {
        $this->config = $this->config->createFromArray($config);
    }

    /**
     * Get the rrdcached address if configured
     */
    public function getRrdcachedAddress(): string
    {
        return $this->config->rrdcached_address;
    }

    /**
     * @throws ConnectionException
     */
    protected function doConnect(): bool
    {
        $this->rrdQueryBuilder->tagStrategy = $this->tagStrategy;

        // Connect using the adapter
        $this->connected = $this->connectionAdapter->connect();

        return $this->connected;
    }

    /**
     * Get the RRD file path for a measurement and tags
     *
     * @param  string  $measurement  The measurement name
     * @param  array<string, string>  $tags  The tags as key-value pairs
     * @return string The full path to the RRD file
     */
    public function getRRDPath(string $measurement, array $tags = []): string
    {
        $filePath = $this->tagStrategy->getFilePath($measurement, $tags);

        if ($this->config->rrdcached_enabled) {
            $filePath = ltrim(str_replace($this->config->rrd_dir, '', $filePath), DIRECTORY_SEPARATOR);
        }

        return $filePath;
    }

    /**
     * Build an rrdtool command with rrdcached support if configured
     *
     * @param  string  $command  The rrdtool command (create, update, fetch, etc.)
     * @param  string[]  $args  The command arguments
     * @return string The command output
     *
     * @throws RRDtoolException
     */
    private function runRrdtoolCommand(string $command, array $args): string
    {
        if ($this->config->debug) {
            $this->logger->debug('Running rrdtool command', [
                'command' => $command,
                'args' => $args,
                'full_command' => $command.' '.implode(' ', $args),
            ]);
        }

        // Execute the command using the connection adapter
        $jsonArgs = json_encode($args);
        if ($jsonArgs === false) {
            throw new RRDtoolException($command, $args, 'Failed to encode command arguments as JSON');
        }
        $response = $this->connectionAdapter->executeCommand($command, $jsonArgs);

        if (! $response->success) {
            if (preg_match('/illegal attempt to update using time \d+ when last update time is/', $response->error ?? '')) {
                throw new RRDtoolPrematureUpdateException($command, $args, $response->error ?? '');
            }

            throw new RRDtoolException($command, $args, $response->error ?? 'Unknown error');
        }

        return $response->data;
    }

    private function guessDataSourceType(mixed $value): string
    {
        if (is_int($value)) {
            return 'GAUGE'; // or COUNTER, DERIVE, ABSOLUTE based on your needs
        }
        if (is_float($value)) {
            return 'GAUGE';
        }

        return 'GAUGE'; // Default fallback
    }

    protected function doWrite(DataPoint $dataPoint): bool
    {
        // Create RRD if it doesn't exist
        $rrdPath = $this->createRRD($dataPoint->getMeasurement(), $dataPoint->getTags(), $dataPoint->getFields());

        // Prepare update string
        $timestamp = $dataPoint->getTimestamp()->getTimestamp();
        $values = [];

        // Get RRD info to determine field order
        $info = $this->getRRDInfo($rrdPath);
        $dataSourceOrder = $this->getDataSourceOrder($info);

        foreach ($dataSourceOrder as $dsName) {
            $fields = $dataPoint->getFields();

            // Use 'U' for any non-numeric value including null
            $values[] = isset($fields[$dsName]) && is_numeric($fields[$dsName]) ? $fields[$dsName] : 'U';
        }

        $updateString = $timestamp.':'.implode(':', $values);

        // Build the command using the helper
        $args = [$rrdPath, $updateString];

        try {
            $this->runRrdtoolCommand('update', $args);

            return true;
        } catch (RRDtoolException $e) {
            throw new WriteException($e->getDebugMessage($this->config->debug), $e->getCode(), $e);
        }
    }

    /**
     * @return array<string, string>
     */
    private function getRRDInfo(string $rrdPath): array
    {
        try {
            $output = $this->runRrdtoolCommand('info', [$rrdPath]);

            $info = [];
            foreach (explode("\n", $output) as $line) {
                if (preg_match('/^([^=]+)\s*=\s*(.+)$/', $line, $matches)) {
                    $info[trim($matches[1])] = trim($matches[2], '"');
                }
            }

            return $info;
        } catch (RRDtoolException) {
            return [];
        }
    }

    /**
     * @param  array<string, string>  $info
     * @return string[]
     */
    private function getDataSourceOrder(array $info): array
    {
        $dataSources = [];
        foreach ($info as $key => $value) {
            if (preg_match('/^ds\[([^]]+)]\.type$/', $key, $matches)) {
                $dataSources[] = $matches[1];
            }
        }

        return $dataSources;
    }

    /**
     * @param  array{'meta': array{'legend': array<int, string>, 'start': int, 'end': int, 'step': int}, 'data': array<int, array<int, float|int|null>>}  $json
     * @param  string[]  $requestedFields
     */
    private function parseRRDXportJson(array $json, array $requestedFields): QueryResult
    {
        // If we have legend data but no matches with requestedFields, use the legend as is
        // This handles the case where the xport command uses different field names than the ones requested
        $legend = array_filter($json['meta']['legend'], fn ($field) => in_array('*', $requestedFields)
            || in_array($field, $requestedFields));

        // If no fields matched, use all legend fields
        if (empty($legend) && ! empty($json['meta']['legend'])) {
            $legend = $json['meta']['legend'];
        }

        $start = $json['meta']['start'];
        $step = $json['meta']['step'];
        $result = new QueryResult(metadata: $json['meta']);

        foreach ($json['data'] as $index => $values) {
            $timestamp = $start + $step * $index;

            foreach ($legend as $field => $name) {
                $result->appendPoint($timestamp, $name, $values[$field]);
            }
        }

        return $result;
    }

    public function rawQuery(RawQueryInterface $query): QueryResult
    {
        if (! $query instanceof RRDtoolRawQuery) {
            throw new RawQueryException($query, 'Invalid query type');
        }

        // For RRDtool, raw query would be a direct rrdtool command
        try {
            $output = $this->runRrdtoolCommand($query->command, $query->getArgs());
            if ($query->command === 'xport') {
                $json = json_decode($output, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new RawQueryException($query, 'Failed to parse RRD command output: '.json_last_error_msg().PHP_EOL.$output, json_last_error());
                }

                /** @var array{'meta': array{'legend': array<int, string>, 'start': int, 'end': int, 'step': int}, 'data': array<int, array<int, float|int|null>>} $json */
                return $this->parseRRDXportJson($json, $query->getFields() ?: ['*']);
            }

            // Create a result with a time key and a value key for non-xport queries
            return new QueryResult([
                'output' => [[
                    'date' => time(),
                    'value' => $output,
                ]],
            ]);
        } catch (RRDtoolException $e) {
            throw new RawQueryException($query, $e->getDebugMessage($this->config->debug), $e->getCode(), $e);
        }
    }

    public function createDatabase(string $database): bool
    {
        // RRDtool doesn't have databases, but we can create a subdirectory
        $dbDir = $this->config->rrd_dir.DIRECTORY_SEPARATOR.$database;

        if (! is_dir($dbDir)) {
            return mkdir($dbDir, 0755, true);
        }

        return true;
    }

    /**
     * @return string[]
     */
    public function getDatabases(): array
    {
        $databases = [];
        $items = scandir($this->config->rrd_dir);

        foreach ($items as $item) {
            if ($item !== '.' && $item !== '..' && is_dir($this->config->rrd_dir.DIRECTORY_SEPARATOR.$item)) {
                $databases[] = $item;
            }
        }

        return $databases;
    }

    public function close(): void
    {
        $this->connectionAdapter->close();
        $this->connected = false;
    }

    /**
     * Check if connected to the database
     *
     * @return bool True if connected
     */
    public function isConnected(): bool
    {
        return $this->connected && $this->connectionAdapter->isConnected();
    }

    /**
     * @param  string  $measurement_or_filename  The tsdb measurement or the full path to the file
     * @param  array<string, string>|null  $tags
     */
    public function rrdExists(string $measurement_or_filename, ?array $tags = null): bool
    {
        $filename = $tags === null ? $measurement_or_filename : $this->getRRDPath($measurement_or_filename, $tags);

        return file_exists($filename);
    }

    /**
     * @param  array<string, string>  $tags
     * @param  array<string, ?scalar>  $fields
     *
     * @throws WriteException
     */
    protected function createRRD(string $measurement, array $tags, array $fields): string
    {
        $rrdPath = $this->getRRDPath($measurement, $tags);

        $dataSources = [];
        foreach ($fields as $field => $value) {
            $type = $this->guessDataSourceType($value);
            $dataSources[] = "DS:{$field}:{$type}:600:U:U";
        }

        $this->createRRDWithCustomConfig($rrdPath, $dataSources);

        return $rrdPath;
    }

    /**
     * @param  string[]  $data_sources
     * @param  string[]|null  $archives
     *
     * @throws WriteException
     */
    public function createRRDWithCustomConfig(string $filename, array $data_sources, ?int $step = null, ?array $archives = null): bool
    {
        if ($this->rrdExists($filename)) {
            return false;
        }

        $step ??= $this->config->default_step;
        $archives ??= $this->config->default_archives;

        if (empty($data_sources)) {
            throw new WriteException('Data sources must be specified for custom RRD creation');
        }

        if (empty($archives)) {
            throw new WriteException('Archives must be specified for custom RRD creation');
        }

        /** @var string[] $archives */
        $args = [$filename, '--step', (string) $step];
        $args = array_merge($args, $data_sources, $archives);

        try {
            $this->runRrdtoolCommand('create', $args);

            return true;
        } catch (RRDtoolException $e) {
            throw new WriteException('Failed to create RRD file: '.$e->getDebugMessage($this->config->debug), $e->getCode(), $e);
        }
    }

    /**
     * @return string filename or raw graph data
     *
     * @throws DriverException
     */
    public function getRRDGraph(RRDtoolRawQuery $graphConfig): string
    {
        try {
            // set up a temp file for output if file output is requested and no file is specified
            if ($this->config->graph_output === 'file' && $graphConfig->filename == '-') {
                $imgFormat = $graphConfig->getParam('--imgformat') ?? $graphConfig->getParam('-a');
                $suffix = $imgFormat ? '.'.strtolower($imgFormat) : '.png';

                $graphConfig->filename = sys_get_temp_dir().DIRECTORY_SEPARATOR.uniqid('rrdgraph_').$suffix;
            }

            $result = $this->runRrdtoolCommand('graph', $graphConfig->getArgs());

            if ($graphConfig->filename == '-') {
                return $result;
            }

            if (! file_exists($graphConfig->filename)) {
                throw new DriverException('Failed to create RRD graph');
            }

            return $graphConfig->filename;
        } catch (RRDtoolException $e) {
            throw new DriverException('Failed to create RRD graph: '.$e->getDebugMessage($this->config->debug), $e->getCode(), $e);
        }
    }
}
